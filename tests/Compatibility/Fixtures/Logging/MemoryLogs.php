<?php

/**
 * Captures encoded log lines and decodes selected records for structured logging
 * assertions.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Logging;

use Phex\Contracts\Logging\LogOutput;
use Phex\Http\Json\JsonObject;

final class MemoryLogs implements LogOutput
{
    /** @var list<string> */
    public array $records = [];

    public function write(string $record): void
    {
        $this->records[] = $record;
    }

    public function record(int $index): JsonObject
    {
        return JsonObject::decode($this->records[$index]);
    }

    public function count(): int
    {
        return count($this->records);
    }
}
