<?php

declare(strict_types=1);

namespace Phex\Runtime\Elephc;

use Phex\Contracts\Runtime\ErrorReporter;
use Throwable;

final class NativeErrorReporter implements ErrorReporter
{
    public function report(Throwable $error): void
    {
        error_log($error->getMessage());
    }
}
