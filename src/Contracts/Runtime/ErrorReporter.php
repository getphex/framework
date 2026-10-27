<?php

declare(strict_types=1);

namespace Phex\Contracts\Runtime;

use Throwable;

interface ErrorReporter
{
    public function report(Throwable $error): void;
}
