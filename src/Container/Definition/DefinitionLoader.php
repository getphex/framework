<?php

declare(strict_types=1);

namespace Phex\Container\Definition;

use InvalidArgumentException;

/** Trusted configuration loader for build hosts and PHP development bootstrap. */
final class DefinitionLoader
{
    public static function container(string $path): Container
    {
        $definition = require $path;
        if (!$definition instanceof Container) {
            throw new InvalidArgumentException('Service configuration must return Container: '.$path);
        }

        return $definition;
    }

    /** @return list<EndpointRoute> */
    public static function routes(string $path): array
    {
        $routes = require $path;
        if (!is_array($routes) || !array_is_list($routes)) {
            throw new InvalidArgumentException('Route configuration must return a list: '.$path);
        }
        $result = [];
        foreach ($routes as $route) {
            if (!$route instanceof EndpointRoute) {
                throw new InvalidArgumentException('Route configuration must contain EndpointRoute values: '.$path);
            }
            $result[] = $route;
        }

        return $result;
    }
}
