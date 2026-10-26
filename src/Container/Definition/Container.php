<?php

declare(strict_types=1);

namespace Phex\Container\Definition;

use Phex\Contracts\Http\Handler;

final readonly class Container
{
    /**
     * @param list<Service<object>> $services
     * @param array<string, class-string> $entries Named, typed public accessors.
     * @param list<EndpointRoute> $routes
     * @param class-string<Handler>|null $handler Optional middleware pipeline root.
     */
    public function __construct(
        public array $services,
        public array $entries = [],
        public array $routes = [],
        public ?string $handler = null,
    ) {}
}
