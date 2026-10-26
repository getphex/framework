<?php

declare(strict_types=1);

use Phex\Build\Compiler\BuildConfig;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\EndpointRoute;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Tests\Compatibility\Fixtures\RoutingCases;
use Phex\Tests\Compatibility\Fixtures\RoutingEchoEndpoint;

$routes = [];
foreach (RoutingCases::definitions() as [$method, $path]) {
    $routes[] = new EndpointRoute($method, $path, RoutingEchoEndpoint::class);
}

return new BuildConfig(new Container([
    Service::constructor(RoutingEchoEndpoint::class, Lifetime::Request),
], routes: $routes), 'Phex\\Tests\\Generated\\RoutingContainer', __DIR__.'/../../var/generated/tests/RoutingContainer.php');
