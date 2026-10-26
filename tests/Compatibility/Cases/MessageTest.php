<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use InvalidArgumentException;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MessageTest extends TestCase
{
    public function testRequestPreservesItsInputsAndBinaryBody(): void
    {
        $headers = new Headers(['Content-Type' => ['application/octet-stream']]);
        $request = new Request('POST', '/upload?part=1', $headers, "a\0b\xff");

        $this->assertSame('POST', $request->method);
        $this->assertSame('/upload?part=1', $request->uri);
        $this->assertSame($headers, $request->headers);
        $this->assertSame("a\0b\xff", $request->body);
    }

    public function testDefaultHeaderObjectsAreIndependent(): void
    {
        $first = new Request('GET', '/');
        $second = new Request('GET', '/');
        $response = new Response();

        $this->assertNotSame($first->headers, $second->headers);
        $this->assertNotSame($first->headers, $response->headers);
        $this->assertSame('', $first->body);
        $this->assertSame(200, $response->status);
        $this->assertSame('', $response->body);
    }

    public function testRequestUpdatesPreserveTheOriginal(): void
    {
        $request = new Request('GET', '/original');
        $changed = $request
            ->withMethod('POST')
            ->withUri('/changed')
            ->withHeaders(new Headers(['X-User' => ['alice']]))
            ->withBody('payload');

        $this->assertSame('GET', $request->method);
        $this->assertSame('/original', $request->uri);
        $this->assertSame('', $request->body);
        $this->assertFalse($request->headers->has('X-User'));
        $this->assertSame('POST', $changed->method);
        $this->assertSame('/changed', $changed->uri);
        $this->assertSame('payload', $changed->body);
        $this->assertSame(['alice'], $changed->headers->values('X-User'));
    }

    public function testResponseUpdatesPreserveTheOriginal(): void
    {
        $response = new Response(200, new Headers(['X-Original' => ['yes']]), 'original');
        $changed = $response
            ->withStatus(201)
            ->withHeaders(new Headers(['Content-Type' => ['application/json']]))
            ->withBody('{"created":true}');

        $this->assertSame(200, $response->status);
        $this->assertSame('original', $response->body);
        $this->assertTrue($response->headers->has('X-Original'));
        $this->assertSame(201, $changed->status);
        $this->assertSame('{"created":true}', $changed->body);
        $this->assertSame(['application/json'], $changed->headers->values('content-type'));
    }

    public function testMethodsAreCaseSensitiveAndAllowExtensionTokens(): void
    {
        $request = new Request('custom-METHOD', '*');

        $this->assertSame('custom-METHOD', $request->method);
        $this->assertSame('*', $request->uri);
    }

    /** @return list<array{string}> */
    public static function invalidMethods(): array
    {
        return [[''], ['GET POST'], ["GET\r\n"], ['GET:'], ["G\0ET"]];
    }

    #[DataProvider('invalidMethods')]
    public function testInvalidMethodsAreRejected(string $method): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Request($method, '/');
    }

    /** @return list<array{string}> */
    public static function invalidUris(): array
    {
        return [
            [''], ['/hello world'], ['/hello#fragment'], ["/hello\r"],
            ["/hello\n"], ["/hello\0"], ["/hello\t"], ["/hello\x7f"],
        ];
    }

    #[DataProvider('invalidUris')]
    public function testInvalidUrisAreRejected(string $uri): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Request('GET', $uri);
    }

    public function testEncodedTargetsArePreservedWithoutDecoding(): void
    {
        $request = new Request('GET', '/hello%20world?value=%23fragment');

        $this->assertSame('/hello%20world?value=%23fragment', $request->uri);
    }

    public function testStatusBoundariesAreAccepted(): void
    {
        $this->assertSame(100, (new Response(100))->status);
        $this->assertSame(599, (new Response(599))->status);
    }

    /** @return list<array{int}> */
    public static function invalidStatuses(): array
    {
        return [[99], [600], [-1]];
    }

    #[DataProvider('invalidStatuses')]
    public function testInvalidStatusesAreRejected(int $status): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Response($status);
    }

    public function testStatusUpdatesAreValidated(): void
    {
        $response = new Response();
        $this->expectException(InvalidArgumentException::class);
        $response->withStatus(600);
    }
}
