<?php

/**
 * Completes the constructor dependency cycle used to verify recursive graph rejection.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class CycleB
{
    public function __construct(public CycleA $next) {}
}
