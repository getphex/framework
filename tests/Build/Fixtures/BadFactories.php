<?php

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
