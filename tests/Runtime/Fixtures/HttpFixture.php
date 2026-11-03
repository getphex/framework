<?php

/**
 * Serves controlled transport responses and echoed input for real PHP/native HTTP socket
 * checks.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Runtime\Fixtures;

use Phex\Contracts\Runtime\RequestReader;
use Phex\Contracts\Runtime\ResponseOutput;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Response;
use Phex\Runtime\Http\BufferedResponseEmitter;

final class HttpFixture
{
    /**
     * @template TReader of RequestReader
     * @template TOutput of ResponseOutput
     * @param TReader $reader
     * @param TOutput $output
     */
    public static function serve(RequestReader $reader, ResponseOutput $output): void
    {
        $request = $reader->read();
        $status = match ($request->uri) {
            '/no-content' => 204,
            '/reset-content' => 205,
            '/not-modified' => 304,
            default => 418,
        };
        $body = $request->method.'|'.$request->uri.'|'.($request->headers->values('X-Test')[0] ?? '').'|'.$request->body;
        $response = new Response($status, new Headers([
            'Content-Type' => ['application/octet-stream'],
            'Set-Cookie' => ['first=1; Path=/', 'second=2; Path=/'],
            'Location' => ['/target'],
            'Content-Length' => ['999'],
        ]), $body);
        (new BufferedResponseEmitter($output))->emit($response, $request->method);
    }
}
