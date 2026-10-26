<?php

declare(strict_types=1);

use Phex\Examples\CompiledContainer\Generated\ApplicationContainer;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Kernel\Application;

require_once __DIR__.'/bootstrap.php';

$container = new ApplicationContainer();
$application = new Application($container);
try {
    $first = $application->handle(new Request('GET', '/users/7', new Headers(['X-Request-Id' => ['alice']])));
    $second = $application->handle(new Request('GET', '/users/42', new Headers(['X-Request-Id' => ['bob']])));
    echo $first->status.' '.$first->body.' '.($first->headers->values('X-Middleware-Id')[0] ?? '')."\n";
    echo $second->status.' '.$second->body.' '.($second->headers->values('X-Middleware-Id')[0] ?? '')."\n";
} finally {
    $container->close();
}
