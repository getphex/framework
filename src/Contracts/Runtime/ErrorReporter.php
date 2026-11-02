<?php

declare(strict_types=1);

namespace Phex\Contracts\Runtime;

use Throwable;

interface ErrorReporter
{
    /** @param array<string, bool|float|int|string|null> $context */
    public function report(Throwable $error, array $context = []): void;
}
