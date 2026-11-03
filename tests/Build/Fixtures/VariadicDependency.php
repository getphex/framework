<?php

/**
 * Supplies a variadic dependency to verify unsupported provider arguments fail during
 * analysis.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class VariadicDependency
{
    /** @var array<int|string, Missing> */
    public array $values;

    public function __construct(Missing ...$value)
    {
        $this->values = $value;
    }
}
