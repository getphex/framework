<?php

/**
 * Matches prevalidated route tables with literal precedence and explicit method fallback
 * behavior.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Routing\Matcher;

use InvalidArgumentException;
use LogicException;
use Phex\Contracts\Routing\Matcher;
use Phex\Http\Message\Request;
use Phex\Routing\Definition\Route;

final readonly class TableMatcher implements Matcher
{
    /** @var list<Route> */
    private array $routes;

    /** @param list<Route> $routes */
    public function __construct(array $routes)
    {
        $ordered = [];
        $names = [];

        foreach ($routes as $item) {
            $route = self::route($item);
            $name = $route->name;
            if ($name !== null) {
                if (isset($names[$name])) {
                    throw new InvalidArgumentException('Duplicate route name: '.$name);
                }
                $names[$name] = true;
            }
            $next = [];
            $inserted = false;

            foreach ($ordered as $item) {
                $current = self::route($item);
                if ($route->method === $current->method && $route->pattern->sameShape($current->pattern)) {
                    throw new InvalidArgumentException('Ambiguous route definition for method '.$route->method.'.');
                }

                if (!$inserted && $route->pattern->precedes($current->pattern)) {
                    $next[] = $route;
                    $inserted = true;
                }

                $next[] = $current;
            }

            if (!$inserted) {
                $next[] = $route;
            }

            $ordered = $next;
        }

        $this->routes = $ordered;
    }

    public function match(Request $request): MatchResult
    {
        try {
            $segments = PathSegments::fromUri($request->uri);
        } catch (InvalidArgumentException) {
            return MatchResult::badRequest();
        }

        $allowed = [];

        foreach ($this->routes as $item) {
            $route = self::route($item);
            $parameters = $route->pattern->match($segments);

            if ($parameters === null) {
                continue;
            }

            if ($route->method === $request->method) {
                return MatchResult::found($route, $parameters);
            }

            if (!in_array($route->method, $allowed, true)) {
                $next = [];
                $inserted = false;

                foreach ($allowed as $current) {
                    if (!$inserted && strcmp($route->method, (string) $current) < 0) {
                        $next[] = $route->method;
                        $inserted = true;
                    }

                    $next[] = (string) $current;
                }

                if (!$inserted) {
                    $next[] = $route->method;
                }

                $allowed = $next;
            }
        }

        if ($allowed === []) {
            return MatchResult::notFound();
        }

        return MatchResult::methodNotAllowed($allowed);
    }

    private static function route(mixed $value): Route
    {
        // ElephC may box elements read from a stored PHP array.
        if (!$value instanceof Route) {
            throw new LogicException('Invalid entry in the immutable routing table.');
        }

        return $value;
    }
}
