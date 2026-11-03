<?php

/**
 * Preserves validated route metadata and parsed segments for matcher and URL-generator
 * emission.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Graph;

use Phex\Contracts\Routing\Endpoint;
use Phex\Routing\Definition\Segment;

/** @internal Validated route metadata for build emitters and PHP development bootstrap. */
final readonly class RouteNode
{
    /**
     * @param class-string<Endpoint> $endpoint
     * @param list<Segment> $segments
     */
    public function __construct(
        public string $method,
        public string $path,
        public string $endpoint,
        public array $segments,
        public ?string $name = null,
    ) {}
}
