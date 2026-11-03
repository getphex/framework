<?php

/**
 * Tracks environment reads and permits controlled value changes in configuration lifetime
 * tests.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Config;

use Phex\Contracts\Config\EnvironmentReader;

final class MutableEnvironment implements EnvironmentReader
{
    public int $reads = 0;

    /** @param array<string, string> $values */
    public function __construct(public array $values = []) {}

    public function get(string $name): ?string
    {
        $this->reads++;

        return $this->values[$name] ?? null;
    }
}
