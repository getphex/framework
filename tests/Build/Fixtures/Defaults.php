<?php

/**
 * Supplies scalar and array defaults with escaped bytes to test deterministic PHP literal
 * emission.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class Defaults
{
    /** @param array<string, int> $map */
    public function __construct(public string $text = "a\"\\\$b\n\x00", public int $min = PHP_INT_MIN, public array $map = ['key' => 7], public float $number = 1.25, public bool $enabled = true) {}
}
