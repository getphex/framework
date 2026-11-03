<?php

/**
 * Defines runtime error reporting with explicit context while keeping transport policy
 * outside the kernel.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Runtime;

use Throwable;

interface ErrorReporter
{
    /** @param array<string, bool|float|int|string|null> $context */
    public function report(Throwable $error, array $context = []): void;
}
