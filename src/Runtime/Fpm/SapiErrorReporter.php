<?php

/**
 * Reports PHP SAPI request failures through the host error log with explicit correlation
 * context.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Fpm;

use Phex\Contracts\Runtime\ErrorReporter;
use Phex\Logging\JsonLogger;
use Phex\Logging\LoggerErrorReporter;
use Phex\Runtime\Logging\SapiLogOutput;
use Throwable;

final class SapiErrorReporter implements ErrorReporter
{
    public function report(Throwable $error, array $context = []): void
    {
        $logger = new JsonLogger(new SapiLogOutput());
        $reporter = new LoggerErrorReporter($logger);
        $reporter->report($error, $context);
    }
}
