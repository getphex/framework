<?php

/**
 * Generates named-route paths from a validated route index for interpreted execution.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Routing\Url;

use InvalidArgumentException;
use LogicException;
use Phex\Contracts\Routing\UrlGenerator;
use Phex\Routing\Definition\Route;

final readonly class TableUrlGenerator implements UrlGenerator
{
    /** @var array<string, Route> */
    private array $routes;

    /** @param list<Route> $routes */
    public function __construct(array $routes)
    {
        $named = [];
        foreach ($routes as $item) {
            $route = self::route($item);
            $name = $route->name;
            if ($name === null) {
                continue;
            }
            if (isset($named[$name])) {
                throw new InvalidArgumentException('Duplicate route name: '.$name);
            }
            $named[$name] = $route;
        }
        $this->routes = $named;
    }

    public function generate(string $name, array $parameters = []): string
    {
        if (!isset($this->routes[$name])) {
            throw new UrlGenerationFailed('Unknown route name: '.$name);
        }
        $route = self::route($this->routes[$name]);
        $segments = $route->pattern->segments();
        $names = [];
        foreach ($segments as $segment) {
            if ($segment->parameter) {
                $names[] = $segment->value;
            }
        }
        UrlParameters::only($parameters, $names);
        $path = '';
        foreach ($segments as $segment) {
            $path .= '/'.($segment->parameter
                ? UrlParameters::segment($parameters, $segment->value)
                : PathEncoder::encode($segment->value));
        }

        return $path === '' ? '/' : $path;
    }

    private static function route(mixed $value): Route
    {
        if (!$value instanceof Route) {
            throw new LogicException('Invalid entry in the immutable URL table.');
        }

        return $value;
    }
}
