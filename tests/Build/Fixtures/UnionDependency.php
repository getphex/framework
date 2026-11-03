<?php

/**
 * Supplies an ambiguous union dependency to verify unsupported constructor signatures are
 * rejected.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class UnionDependency
{
    public function __construct(public Missing|CycleA $value) {}
}
