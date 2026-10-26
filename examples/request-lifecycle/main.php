<?php

declare(strict_types=1);

use Phex\Examples\JsonApi\JsonApi;
use Phex\Examples\RequestLifecycle\LifecycleFactory;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Kernel\Application;

require __DIR__.'/bootstrap.php';

$application = new Application(new LifecycleFactory(JsonApi::application()));

foreach ([
    new Request('GET', '/users/7', new Headers(['X-Request-Id' => ['alice']])),
    new Request('GET', '/users/42', new Headers(['X-Request-Id' => ['bob']])),
] as $request) {
    $response = $application->handle($request);
    $ids = $response->headers->values('X-Request-Id');
    echo $response->status, ' ', $response->body, ' ', $ids[0], "\n";
}
