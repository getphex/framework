<?php

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

use Phex\Contracts\Runtime\RequestReader;
use Phex\Contracts\Runtime\ResponseOutput;
use Phex\Examples\CompiledContainer\Generated\ApplicationContainer;
use Phex\Http\Message\Response;
use Phex\Kernel\Application;
use Phex\Runtime\Http\BufferedResponseEmitter;
use Phex\Runtime\Http\InvalidRequest;
use Throwable;

final class WebApplication
{
    /**
     * @template TReader of RequestReader
     * @template TOutput of ResponseOutput
     * @param TReader $reader
     * @param TOutput $output
     */
    public static function serve(RequestReader $reader, ResponseOutput $output, string $requestMethod): void
    {
        try {
            $container = new ApplicationContainer();
            try {
                $request = $reader->read();
                $requestMethod = $request->method;
                $response = (new Application($container))->handle($request);
            } finally {
                $container->close();
            }
        } catch (InvalidRequest) {
            $response = new Response(status: 400, body: 'Bad Request');
        } catch (Throwable $error) {
            error_log((string) $error);
            $response = new Response(status: 500, body: 'Internal Server Error');
        }

        $response = $response->withHeaders($response->headers->with('Content-Type', ['text/plain; charset=utf-8']));
        (new BufferedResponseEmitter($output))->emit($response, $requestMethod);
    }
}
