<?php

/**
 * Defines shared route patterns and request targets for differential compiled-versus-table
 * matcher tests.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Container\Routing\EndpointReference;
use Phex\Routing\Definition\Route;
use Phex\Routing\Matcher\TableMatcher;

final class RoutingCases
{
    /** @return list<array{string, string}> */
    public static function definitions(): array
    {
        return [
            ['GET', '/'], ['HEAD', '/'],
            ['GET', '/users/{id}'], ['GET', '/{section}/me'],
            ['POST', '/users/me'], ['GET', '/users/me'],
            ['DELETE', '/users/{name}'], ['PATCH', '/users/new'],
            ['GET', '/users/{id}/posts/{post}'], ['PUT', '/teams/{team}/users/{user}'],
            ['GET', '/files/{name}'], ['GET', '/hello world'], ['GET', '/caf'."\xc3\xa9"],
            ['GET', '/literal+plus'], ['GET', '/literal%name'], ['GET', '/users/'],
            ['GET', '/a//b'], ['POST', '/0/{code}'], ['GET', '/dots/../{name}'],
            ['GET', '/raw/a%2Fb'], ['GET', '/Case'],
            ['GET', '/method/{id}'], ['POST', '/method/me'],
            ['GET', '/people/{name}'], ['POST', '/people/{identifier}'],
            ['HEAD', '/head'], ['OPTIONS', '/head'],
            ['2', '/numeric'], ['10', '/numeric'],
            ['GET', '/quote"/{value}'], ['GET', '/dollar$/{value}'],
            ['GET', '/back\\slash/{value}'], ['GET', '/byte'."\xff".'/{value}'],
        ];
    }

    /** @return list<string> */
    public static function targets(): array
    {
        return [
            '/', '/?ignored=%GG', '/users/7', '/users/me', '/users/new', '/else/me',
            '/users/', '/users/42/', '/Users/7', '/users/42/posts/10',
            '/teams/7/users/42', '/files/a%2Fb', '/files/%252F', '/files/a+b',
            '/files/a%2Bb', '/files/a%20b%3F', '/files/', '/files/a/b',
            '/hello%20world', '/caf%C3%A9', '/literal+plus', '/literal%25name',
            '/a//b', '/a/b', '/0/code', '/dots/../name', '/raw/a%252Fb',
            '/Case', '/case', '/method/me', '/people/alice', '/head', '/numeric',
            '/quote%22/value', '/dollar$/value', '/back%5Cslash/value', '/byte%FF/value',
            '/missing',
        ];
    }

    public static function table(): TableMatcher
    {
        $routes = [];
        foreach (self::definitions() as $definition) {
            $routes[] = new Route((string) $definition[0], (string) $definition[1], new EndpointReference(RoutingEchoEndpoint::class));
        }

        return new TableMatcher($routes);
    }
}
