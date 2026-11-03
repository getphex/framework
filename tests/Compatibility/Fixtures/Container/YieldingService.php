<?php

/**
 * Suspends during service construction to exercise reentrant or interleaved request
 * resolution guards.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Container;

use Fiber;

final class YieldingService
{
    public static function create(): YieldingService
    {
        Fiber::suspend();

        return new YieldingService();
    }
}
