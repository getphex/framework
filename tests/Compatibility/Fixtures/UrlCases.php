<?php

/**
 * Defines named-route fixtures shared by interpreted and compiled URL generation tests.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Routing\Definition\Route;
use Phex\Routing\Url\TableUrlGenerator;

final class UrlCases
{
    /** @return list<array{string, string, string}> */
    public static function definitions(): array
    {
        return [
            ['home', 'GET', '/'],
            ['users.show', 'GET', '/users/{id}'],
            ['users.update', 'PUT', '/users/{id}'],
            ['files.show', 'GET', '/files/{name}'],
            ['teams.user', 'GET', '/teams/{team}/users/{id}'],
            ['literal', 'GET', '/literal+percent%/caf'."\xc3\xa9"],
            ['trailing', 'GET', '/a//{name}/'],
            ['quotes', 'GET', '/quote"/dollar$/back\\slash'],
            ['Upper_Case-2', 'GET', '/upper'],
        ];
    }

    /** @return list<Route> */
    public static function routes(): array
    {
        $routes = [];
        foreach (self::definitions() as $definition) {
            $routes[] = new Route((string) $definition[1], (string) $definition[2], new RoutingEchoEndpoint(), name: (string) $definition[0]);
        }
        $routes[] = new Route('GET', '/unnamed', new RoutingEchoEndpoint());

        return $routes;
    }

    public static function table(): TableUrlGenerator
    {
        return new TableUrlGenerator(self::routes());
    }
}
