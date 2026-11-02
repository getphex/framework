<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Runtime\ErrorReporter;
use Throwable;

final class Errors implements ErrorReporter
{
    public string $message = '';
    /** @var array<string, bool|float|int|string|null> */
    public array $context = [];

    public function report(Throwable $error, array $context = []): void
    {
        $this->message = $error->getMessage();
        $this->context = $context;
    }
}
