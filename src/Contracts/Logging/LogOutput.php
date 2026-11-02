<?php

declare(strict_types=1);

namespace Phex\Contracts\Logging;

interface LogOutput
{
    /** Write one complete JSON record, without a trailing newline. */
    public function write(string $record): void;
}
