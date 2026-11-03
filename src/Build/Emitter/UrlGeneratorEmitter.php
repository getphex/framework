<?php

/**
 * Emits named-route URL generation with pre-encoded literals and direct parameter lookups.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Emitter;

use Phex\Build\Graph\RouteNode;
use Phex\Routing\Url\PathEncoder;

/** @internal Emit fixed name branches and pre-encoded literal path segments. */
final class UrlGeneratorEmitter
{
    /** @param list<RouteNode> $routes */
    public function emit(array $routes, string $className): string
    {
        $named = [];
        foreach ($routes as $route) {
            if ($route->name !== null) {
                $named[$route->name] = $route;
            }
        }
        ksort($named, SORT_STRING);
        $source = "\nfinal readonly class ".$className." implements \\Phex\\Contracts\\Routing\\UrlGenerator\n{\n";
        $source .= "    /** @param array<string, string|int> \$parameters */\n    public function generate(string \$name, array \$parameters = []): string\n    {\n";
        foreach ($named as $name => $route) {
            $names = [];
            $parts = [];
            $literal = '';
            foreach ($route->segments as $segment) {
                $literal .= '/';
                if ($segment->parameter) {
                    $names[] = $segment->value;
                    $parts[] = Literal::emit($literal);
                    $literal = '';
                    $parts[] = '\\Phex\\Routing\\Url\\UrlParameters::segment($parameters, '.Literal::emit($segment->value).')';
                } else {
                    $literal .= PathEncoder::encode($segment->value);
                }
            }
            if ($literal !== '' || $parts === []) {
                $parts[] = Literal::emit($parts === [] && $literal === '' ? '/' : $literal);
            }
            $source .= '        if ($name === '.Literal::emit($name).") {\n";
            $source .= '            \\Phex\\Routing\\Url\\UrlParameters::only($parameters, '.Literal::emit($names).");\n";
            $source .= '            return '.implode('.', $parts).";\n        }\n";
        }

        return $source."        throw new \\Phex\\Routing\\Url\\UrlGenerationFailed('Unknown route name: '.\$name);\n    }\n}\n";
    }
}
