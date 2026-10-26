<?php

declare(strict_types=1);

use Phex\Http\Message\Headers;
use Phex\Http\Message\Response;
use Phex\Runtime\Fpm\GlobalsRequestReader;
use Phex\Runtime\Fpm\SapiOutput;
use Phex\Runtime\Http\BufferedResponseEmitter;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$request = (new GlobalsRequestReader())->read();
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
(new BufferedResponseEmitter(new SapiOutput()))->emit($response, $request->method);
