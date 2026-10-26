<?php

declare(strict_types=1);

namespace Phex\Build\Graph;

use Phex\Container\Definition\Lifetime;

/** @internal */
final readonly class Node
{
    /** @param list<Argument> $arguments */
    public function __construct(
        public string $type,
        public Lifetime $lifetime,
        public string $call,
        public array $arguments,
        public bool $resource,
        public bool $possibleResource,
    ) {}
}
