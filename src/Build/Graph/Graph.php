<?php

declare(strict_types=1);

namespace Phex\Build\Graph;

use Phex\Container\Definition\Container;

/** @internal */
final readonly class Graph
{
    /**
     * @param array<string, Node> $nodes
     * @param list<RouteNode> $routes
     */
    public function __construct(public array $nodes, public Container $definition, public array $routes = []) {}
}
