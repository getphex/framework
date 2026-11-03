<?php

/**
 * Provides one side of a constructor dependency cycle rejected by the container analyzer.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class CycleA
{
    public function __construct(public CycleB $next) {}
}
