<?php

declare(strict_types=1);

namespace Phex\Build\Graph;

use Phex\Contracts\Routing\Endpoint;
use Phex\Routing\Definition\Segment;

/** @internal Validated route metadata, consumed only by build emitters. */
final readonly class RouteNode
{
    /**
     * @param class-string<Endpoint> $endpoint
     * @param list<Segment> $segments
     */
    public function __construct(public string $method, public string $path, public string $endpoint, public array $segments) {}
}
