<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use Phex\Http\Message\Headers;
use Phex\Http\Message\Response;
use Phex\Runtime\Http\BufferedResponseEmitter;
use Phex\Tests\Compatibility\Fixtures\HttpOutput;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BufferedResponseEmitterTest extends TestCase
{
    public function testStatusRepeatedFieldsAndBinaryContentAreEmittedInOrder(): void
    {
        $output = new HttpOutput();
        $response = new Response(201, new Headers([
            'Set-Cookie' => ['first=1; Path=/', 'second=2; Path=/'],
            'Content-Type' => ['application/octet-stream'],
            'Content-Length' => ['999'],
            'Transfer-Encoding' => ['chunked'],
            'Trailer' => ['X-Checksum'],
        ]), "a\0\xFF");
        (new BufferedResponseEmitter($output))->emit($response, 'POST');
        $this->assertSame(201, $output->status);
        $this->assertSame(['start', 'body'], $output->events);
        $this->assertSame(['first=1; Path=/', 'second=2; Path=/'], $output->headers()->values('Set-Cookie'));
        $this->assertSame(['3'], $output->headers()->values('Content-Length'));
        $this->assertFalse($output->headers()->has('Transfer-Encoding'));
        $this->assertFalse($output->headers()->has('Trailer'));
        $this->assertSame("a\0\xFF", $output->body);
        $this->assertSame(['999'], $response->headers->values('Content-Length'));
    }

    public function testHeadSendsTheRepresentationLengthWithoutWritingItsBody(): void
    {
        $output = new HttpOutput();
        (new BufferedResponseEmitter($output))->emit(new Response(body: 'hello'), 'HEAD');
        $this->assertSame(['5'], $output->headers()->values('Content-Length'));
        $this->assertSame(['start'], $output->events);
        $this->assertSame('', $output->body);
    }

    /** @return list<array{int, string}> */
    public static function responsesWithoutContent(): array
    {
        return [[100, 'GET'], [103, 'GET'], [204, 'GET'], [304, 'GET'], [200, 'CONNECT']];
    }

    #[DataProvider('responsesWithoutContent')]
    public function testBodylessStatusesDoNotEmitContentOrInferLength(int $status, string $method): void
    {
        $output = new HttpOutput();
        (new BufferedResponseEmitter($output))->emit(new Response($status, new Headers(['Content-Length' => ['999']]), 'ignored'), $method);
        $this->assertSame($status, $output->status);
        $this->assertSame(['start'], $output->events);
        $this->assertFalse($output->headers()->has('Content-Length'));
        $this->assertSame('', $output->body);
    }

    public function testResetContentEmitsZeroLength(): void
    {
        $output = new HttpOutput();
        (new BufferedResponseEmitter($output))->emit(new Response(205, body: 'ignored'), 'GET');
        $this->assertSame(['0'], $output->headers()->values('Content-Length'));
        $this->assertSame(['start'], $output->events);
    }

    public function testTheEmitterKeepsNoResponseStateBetweenCalls(): void
    {
        $output = new HttpOutput();
        $emitter = new BufferedResponseEmitter($output);
        $emitter->emit(new Response(body: 'first'), 'GET');
        $emitter->emit(new Response(404), 'HEAD');
        $this->assertSame(404, $output->status);
        $this->assertSame(['0'], $output->headers()->values('Content-Length'));
        $this->assertSame('first', $output->body);
        $this->assertSame(['start', 'body', 'start'], $output->events);
    }
}
