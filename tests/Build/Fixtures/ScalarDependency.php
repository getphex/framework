<?php

/**
 * Supplies a required scalar constructor parameter without a supported service binding.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class ScalarDependency
{
    public function __construct(public string $value) {}
}
