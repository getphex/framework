<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Runtime\ErrorReporter;
use Throwable;

final class Errors implements ErrorReporter
{
    public string $message = '';

    public function report(Throwable $error): void
    {
        $this->message = $error->getMessage();
    }
}
