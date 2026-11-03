<?php

/**
 * Verifies CGI metadata validation, header mapping and request isolation with binary body
 * preservation.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use LogicException;
use Phex\Runtime\Fpm\ServerRequest;
use Phex\Runtime\Http\InvalidRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServerRequestTest extends TestCase
{
    public function testMetadataAndBinaryBodiesArePreserved(): void
    {
        $request = ServerRequest::create([
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/users/a%2Fb?name=a+b',
            'HTTP_HOST' => 'localhost:8080',
            'HTTP_X_REQUEST_ID' => 'alice',
            'HTTP_ACCEPT' => 'text/plain, application/json;q=0.5',
            'HTTP_COOKIE' => 'a=1; b=2',
            'CONTENT_TYPE' => 'application/octet-stream',
            'CONTENT_LENGTH' => '3',
            'SERVER_NAME' => 'ignored.internal',
            'argv' => ['ignored'],
            0 => 'ignored',
        ], "a\0\xFF");

        $this->assertSame('POST', $request->method);
        $this->assertSame('/users/a%2Fb?name=a+b', $request->uri);
        $this->assertSame("a\0\xFF", $request->body);
        $this->assertSame(['alice'], $request->headers->values('x-request-id'));
        $this->assertSame(['localhost:8080'], $request->headers->values('host'));
        $this->assertSame(['text/plain, application/json;q=0.5'], $request->headers->values('accept'));
        $this->assertSame(['a=1; b=2'], $request->headers->values('cookie'));
        $this->assertSame(['application/octet-stream'], $request->headers->values('content-type'));
        $this->assertSame(['3'], $request->headers->values('content-length'));
        $this->assertFalse($request->headers->has('server-name'));
    }

    public function testContentMetadataTakesPrecedenceOverHttpAliases(): void
    {
        $request = ServerRequest::create([
            'REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/',
            'HTTP_CONTENT_TYPE' => 'wrong', 'CONTENT_TYPE' => 'text/plain',
            'HTTP_CONTENT_LENGTH' => '999', 'CONTENT_LENGTH' => '0',
            'HTTP_X_EMPTY' => '',
        ], '');
        $this->assertSame(['text/plain'], $request->headers->values('Content-Type'));
        $this->assertSame(['0'], $request->headers->values('Content-Length'));
        $this->assertSame([''], $request->headers->values('X-Empty'));
    }

    public function testRequestsDoNotRetainServerStateBetweenCalls(): void
    {
        $first = ServerRequest::create(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/one', 'HTTP_X_ID' => 'one'], 'first');
        $second = ServerRequest::create(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/two'], '');
        $this->assertSame(['one'], $first->headers->values('X-Id'));
        $this->assertFalse($second->headers->has('X-Id'));
        $this->assertSame('', $second->body);
    }

    /** @return list<array{array<string, mixed>}> */
    public static function invalidMetadata(): array
    {
        return [
            [[]],
            [['REQUEST_METHOD' => 'GET']],
            [['REQUEST_METHOD' => 1, 'REQUEST_URI' => '/']],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => []]],
            [['REQUEST_METHOD' => '', 'REQUEST_URI' => '/']],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/bad path']],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_X_TEST' => ['bad']]],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_X_TEST' => "bad\r\nvalue"]],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'HTTP_' => 'bad']],
            [['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'CONTENT_TYPE' => null]],
        ];
    }

    #[DataProvider('invalidMetadata')]
    public function testInvalidTransportMetadataRaisesAnInputError(mixed $server): void
    {
        // The native test runner boxes nested mixed-array provider arguments.
        if (!is_array($server)) {
            throw new LogicException('Expected server metadata from the data provider.');
        }
        $this->expectException(InvalidRequest::class);
        ServerRequest::create((array) $server, '');
    }
}
