<?php

/**
 * Writes complete structured log lines to the web SAPI error output.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Logging;

use Phex\Contracts\Logging\LogOutput;
use RuntimeException;

final class SapiLogOutput implements LogOutput
{
    public function write(string $record): void
    {
        if (!error_log($record)) {
            throw new RuntimeException('Failed to write the application log.');
        }
    }
}
