<?php

declare(strict_types=1);

namespace Phex\Runtime\Http;

use Phex\Contracts\Container\ApplicationContainer;
use Phex\Contracts\Runtime\RequestReader;
use Phex\Contracts\Runtime\ResponseOutput;
use Phex\Contracts\Runtime\ErrorReporter;
use Phex\Contracts\Runtime\RequestIdGenerator;
use Phex\Http\Message\Response;
use Phex\Kernel\Application;
use Throwable;

final class WebApplication
{
    /**
     * @template TReader of RequestReader
     * @template TOutput of ResponseOutput
     * @template TContainer of ApplicationContainer
     * @template TReporter of ErrorReporter
     * @param TReader $reader
     * @param TOutput $output
     * @param callable(): TContainer $factory
     * @param TReporter $errors
     */
    public static function serve(RequestReader $reader, ResponseOutput $output, callable $factory, ErrorReporter $errors, string $requestMethod = 'GET', ?RequestIdGenerator $requestIds = null): void
    {
        \Phex\Runtime\Http\WebApplication::dispatch($reader, $output, fn (): ApplicationContainer => $factory(), $errors, $requestMethod, $requestIds, true);
    }

    /**
     * @internal The owning persistent host closes the container at shutdown.
     * @template TReader of RequestReader
     * @template TOutput of ResponseOutput
     * @param TReader $reader
     * @param TOutput $output
     */
    public static function serveRetained(RequestReader $reader, ResponseOutput $output, ApplicationContainer $container, ErrorReporter $errors, string $requestMethod, ?RequestIdGenerator $requestIds): void
    {
        \Phex\Runtime\Http\WebApplication::dispatch($reader, $output, fn (): ApplicationContainer => $container, $errors, $requestMethod, $requestIds, false);
    }

    /**
     * @template TReader of RequestReader
     * @template TOutput of ResponseOutput
     * @template TContainer of ApplicationContainer
     * @template TReporter of ErrorReporter
     * @param TReader $reader
     * @param TOutput $output
     * @param callable(): TContainer $factory
     * @param TReporter $errors
     */
    private static function dispatch(RequestReader $reader, ResponseOutput $output, callable $factory, ErrorReporter $errors, string $requestMethod, ?RequestIdGenerator $requestIds, bool $closeContainer): void
    {
        $requestId = '';
        $context = [];
        try {
            if ($requestIds !== null) {
                $generatedId = $requestIds->generate();
                $requestId = $generatedId->value;
                $context['request_id'] = $requestId;
            }
            $container = $factory();
            try {
                $request = $reader->read();
                $requestMethod = $request->method;
                $context['method'] = $requestMethod;
                if ($requestId !== '') {
                    $request = $request->withHeaders($request->headers->with('X-Request-Id', [$requestId]));
                }
                $response = (new Application($container))->handle($request);
            } finally {
                if ($closeContainer) {
                    $container->close();
                }
            }
        } catch (InvalidRequest) {
            $response = new Response(status: 400, body: 'Bad Request');
        } catch (Throwable $error) {
            $errors->report($error, $context);
            $response = new Response(status: 500, body: 'Internal Server Error');
        }
        if ($requestId !== '') {
            $response = $response->withHeaders($response->headers->with('X-Request-Id', [$requestId]));
        }
        if (!$response->headers->has('Content-Type')) {
            $response = $response->withHeaders($response->headers->with('Content-Type', ['text/plain; charset=utf-8']));
        }
        (new BufferedResponseEmitter($output))->emit($response, $requestMethod);
    }
}
