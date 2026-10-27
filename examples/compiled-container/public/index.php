<?php

declare(strict_types=1);

use Phex\Build\Compiler\BuildConfig;
use Phex\Http\Message\Response;
use Phex\Kernel\Application;
use Phex\Runtime\Development\DevelopmentContainer;
use Phex\Runtime\Fpm\GlobalsRequestReader;
use Phex\Runtime\Fpm\SapiOutput;
use Phex\Runtime\Http\BufferedResponseEmitter;
use Phex\Runtime\Http\InvalidRequest;

require_once __DIR__.'/../../../vendor/autoload.php';

$requestMethod = ($_SERVER['REQUEST_METHOD'] ?? null) === 'HEAD' ? 'HEAD' : 'GET';

try {
    $config = require __DIR__.'/../container.php';
    if (!$config instanceof BuildConfig) {
        throw new LogicException('The example configuration must return BuildConfig.');
    }
    $container = new DevelopmentContainer($config->container);
    try {
        $request = (new GlobalsRequestReader())->read();
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
(new BufferedResponseEmitter(new SapiOutput()))->emit($response, $requestMethod);
