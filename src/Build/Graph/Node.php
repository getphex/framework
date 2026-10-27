<?php

declare(strict_types=1);

namespace Phex\Build\Graph;

use Phex\Container\Definition\Lifetime;

/** @internal */
final readonly class Node
{
    /**
     * @param class-string $type
     * @param class-string $provider
     * @param list<Argument> $arguments
     */
    public function __construct(
        public string $type,
        public Lifetime $lifetime,
        public string $provider,
        public ?string $method,
        public array $arguments,
        public bool $resource,
        public bool $possibleResource,
    ) {}
}
