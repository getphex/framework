<?php

declare(strict_types=1);

namespace Phex\Runtime\Fpm;

use Phex\Contracts\Runtime\ErrorReporter;
use Throwable;

final class SapiErrorReporter implements ErrorReporter
{
    public function report(Throwable $error): void
    {
        error_log((string) $error);
    }
}
