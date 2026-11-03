<?php

/**
 * Supplies invalid factory signatures and visibility cases for static dependency graph
 * validation tests.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class BadFactories
{
    public static function nullable(bool $empty = false): ?Missing { return $empty ? null : new Missing(); }
    private static function hidden(): Missing { return new Missing(); }
    public static function wrapper(): Missing { return self::hidden(); }
    public function instance(): Missing { return new Missing(); }
    public static function scalar(): string { return 'bad'; }
    public static function wrong(): CycleA { throw new \RuntimeException(); }
}
