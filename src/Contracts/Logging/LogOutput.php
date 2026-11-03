<?php

/**
 * Defines the output boundary for complete encoded log records across runtime adapters.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Logging;

interface LogOutput
{
    /** Write one complete JSON record, without a trailing newline. */
    public function write(string $record): void;
}
