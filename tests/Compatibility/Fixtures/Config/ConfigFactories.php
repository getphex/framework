<?php

/**
 * Counts configuration factory calls and supplies mutable reader data for runtime/build
 * isolation tests.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Config;

final class ConfigFactories
{
    public static int $calls = 0;

    public static function reader(): MutableEnvironment
    {
        self::$calls++;

        return new MutableEnvironment([
            'PHEX_GREETING' => 'first',
            'PHEX_PAGE_SIZE' => '7',
            'PHEX_UPPERCASE_GREETING' => 'false',
        ]);
    }
}
