<?php

/**
 * Accumulates literal and parameter branches with route IDs for compiled prefix matching.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Graph;

/** @internal A build-time prefix node. It is never instantiated during requests. */
final class RouteBranch
{
    /** @var array<string, RouteBranch> */
    public array $literals = [];

    public ?RouteBranch $parameter = null;

    /** @var list<int> Stable route IDs at this leaf. */
    public array $routes = [];

    public function __construct(public readonly string $literal = '') {}
}
