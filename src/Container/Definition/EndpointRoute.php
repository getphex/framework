<?php

declare(strict_types=1);

namespace Phex\Container\Definition;

use Phex\Contracts\Routing\Endpoint;

final readonly class EndpointRoute
{
    /** @param class-string<Endpoint> $endpoint */
    public function __construct(
        public string $method,
        public string $path,
        public string $endpoint,
        public ?string $name = null,
    ) {}
}
