<?php

/**
 * Builds the named-route fixture used to compare generated and interpreted URL generation.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Phex\Build\Compiler\BuildConfig;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\EndpointRoute;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Tests\Compatibility\Fixtures\RoutingEchoEndpoint;
use Phex\Tests\Compatibility\Fixtures\UrlCases;
use Phex\Tests\Compatibility\Fixtures\UrlConsumer;

$routes = [];
foreach (UrlCases::definitions() as [$name, $method, $path]) {
    $routes[] = new EndpointRoute($method, $path, RoutingEchoEndpoint::class, name: $name);
}
$routes[] = new EndpointRoute('GET', '/unnamed', RoutingEchoEndpoint::class);

return new BuildConfig(new Container([
    Service::constructor(RoutingEchoEndpoint::class, Lifetime::Request),
    Service::constructor(UrlConsumer::class, Lifetime::Application),
], ['links' => UrlConsumer::class], $routes),
    'Phex\\Tests\\Generated\\NamedRoutingContainer',
    __DIR__.'/../../var/generated/tests/NamedRoutingContainer.php',
);
