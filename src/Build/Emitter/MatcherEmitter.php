<?php

declare(strict_types=1);

namespace Phex\Build\Emitter;

use Phex\Build\Graph\RouteBranch;
use Phex\Build\Graph\RouteNode;

/** @internal Emit a fixed prefix search; no runtime tree construction or pattern parsing. */
final class MatcherEmitter
{
    /** @param list<RouteNode> $routes */
    public function emit(array $routes, string $className): string
    {
        usort($routes, static fn (RouteNode $a, RouteNode $b): int => strcmp($a->path, $b->path)
            ?: strcmp($a->method, $b->method) ?: strcmp($a->endpoint, $b->endpoint));
        $groups = [];
        $source = "\nfinal readonly class ".$className.' implements \\Phex\\Contracts\\Routing\\Matcher'."\n{\n";
        foreach ($routes as $id => $route) {
            $source .= '    private \\Phex\\Routing\\Definition\\Route $route'.$id.";\n";
            $length = count($route->segments);
            $branch = $groups[$length] ??= new RouteBranch();
            foreach ($route->segments as $segment) {
                if ($segment->parameter) {
                    $branch->parameter ??= new RouteBranch();
                    $branch = $branch->parameter;
                } else {
                    // Prefix keys so numeric literals never turn into integer array keys.
                    $key = 'literal:'.$segment->value;
                    $branch->literals[$key] ??= new RouteBranch($segment->value);
                    $branch = $branch->literals[$key];
                }
            }
            $branch->routes[] = $id;
        }
        ksort($groups, SORT_NUMERIC);
        $source .= "\n    public function __construct()\n    {\n";
        foreach ($routes as $id => $route) {
            $segments = [];
            foreach ($route->segments as $segment) {
                $segments[] = 'new \\Phex\\Routing\\Definition\\Segment('.Literal::emit($segment->value).', '.($segment->parameter ? 'true' : 'false').')';
            }
            $source .= '        $this->route'.$id.' = new \\Phex\\Routing\\Definition\\Route('
                .Literal::emit($route->method).', '.Literal::emit($route->path)
                .', new \\Phex\\Container\\Routing\\EndpointReference(\\'.$route->endpoint.'::class), compiledSegments: ['.implode(', ', $segments)."]);\n";
        }
        $source .= "    }\n\n    public function match(\\Phex\\Http\\Message\\Request \$request): \\Phex\\Routing\\Matcher\\MatchResult\n    {\n        try {\n            \$parts = \\Phex\\Routing\\Matcher\\PathSegments::fromUri(\$request->uri);\n        } catch (\\InvalidArgumentException) {\n            return \\Phex\\Routing\\Matcher\\MatchResult::badRequest();\n        }\n";
        if ($groups !== []) {
            $source .= "        \$count = count(\$parts);\n";
        }
        foreach ($groups as $length => $branch) {
            $source .= '        if ($count === '.$length.") {\n";
            if ($length === 0) {
                $source .= $this->leaf($branch, $routes, [], '            ');
                $methods = $this->methods($branch, $routes);
                $source .= '            return \\Phex\\Routing\\Matcher\\MatchResult::methodNotAllowed('.Literal::emit($methods).");\n";
            } else {
                for ($index = 0; $index < $length; $index++) {
                    $source .= '            $part'.$index.' = (string) $parts['.$index."];\n";
                }
                $flags = [];
                foreach ($this->methods($branch, $routes) as $index => $method) {
                    $flags['method:'.$method] = '$allow'.$index;
                    $source .= '            $allow'.$index." = false;\n";
                }
                $source .= $this->branch($branch, 0, $routes, $flags, '            ');
                $source .= "            \$allowed = [];\n";
                foreach ($this->methods($branch, $routes) as $method) {
                    $source .= '            if ('.$flags['method:'.$method].") {\n                \$allowed[] = ".Literal::emit($method).";\n            }\n";
                }
                $source .= "            if (\$allowed !== []) {\n                return \\Phex\\Routing\\Matcher\\MatchResult::methodNotAllowed(\$allowed);\n            }\n            return \\Phex\\Routing\\Matcher\\MatchResult::notFound();\n";
            }
            $source .= "        }\n";
        }

        return $source."        return \\Phex\\Routing\\Matcher\\MatchResult::notFound();\n    }\n}\n";
    }

    /**
     * @param list<RouteNode> $routes
     * @param array<string, string> $flags
     */
    private function branch(RouteBranch $branch, int $depth, array $routes, array $flags, string $indent): string
    {
        if ($branch->routes !== []) {
            return $this->leaf($branch, $routes, $flags, $indent);
        }
        $source = '';
        $literals = $branch->literals;
        ksort($literals, SORT_STRING);
        foreach ($literals as $child) {
            $source .= $indent.'if ($part'.$depth.' === '.Literal::emit($child->literal).") {\n"
                .$this->branch($child, $depth + 1, $routes, $flags, $indent.'    ').$indent."}\n";
        }
        if ($branch->parameter !== null) {
            // Independent branches preserve method fallback after a literal path mismatch.
            $source .= $indent.'if ($part'.$depth." !== '') {\n"
                .$this->branch($branch->parameter, $depth + 1, $routes, $flags, $indent.'    ').$indent."}\n";
        }

        return $source;
    }

    /**
     * @param list<RouteNode> $routes
     * @param array<string, string> $flags
     */
    private function leaf(RouteBranch $branch, array $routes, array $flags, string $indent): string
    {
        $source = '';
        foreach ($branch->routes as $id) {
            $route = $routes[$id];
            $captures = [];
            foreach ($route->segments as $index => $segment) {
                if ($segment->parameter) {
                    $captures[] = Literal::emit($segment->value).' => $part'.$index;
                }
            }
            $source .= $indent.'if ($request->method === '.Literal::emit($route->method).") {\n"
                .$indent.'    return \\Phex\\Routing\\Matcher\\MatchResult::found($this->route'.$id.', new \\Phex\\Routing\\Parameter\\Parameters(['.implode(', ', $captures)."]));\n".$indent."}\n";
        }
        foreach ($branch->routes as $id) {
            if ($flags !== []) {
                $source .= $indent.$flags['method:'.$routes[$id]->method]." = true;\n";
            }
        }

        return $source;
    }

    /**
     * @param list<RouteNode> $routes
     * @return list<string>
     */
    private function methods(RouteBranch $branch, array $routes): array
    {
        $methods = [];
        foreach ($branch->routes as $id) {
            $method = $routes[$id]->method;
            $methods['method:'.$method] = $method;
        }
        foreach ($branch->literals as $child) {
            foreach ($this->methods($child, $routes) as $method) {
                $methods['method:'.$method] = $method;
            }
        }
        if ($branch->parameter !== null) {
            foreach ($this->methods($branch->parameter, $routes) as $method) {
                $methods['method:'.$method] = $method;
            }
        }
        $result = array_values($methods);
        sort($result, SORT_STRING);

        return $result;
    }
}
