<?php

/**
 * Reports native web failures without depending on unsupported exception-object
 * conversions.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Elephc;

use Phex\Contracts\Runtime\ErrorReporter;
use Phex\Logging\JsonLogger;
use Phex\Logging\LoggerErrorReporter;
use Phex\Runtime\Logging\SapiLogOutput;
use Throwable;

final class NativeErrorReporter implements ErrorReporter
{
    public function report(Throwable $error, array $context = []): void
    {
        $logger = new JsonLogger(new SapiLogOutput());
        $reporter = new LoggerErrorReporter($logger);
        $reporter->report($error, $context);
    }
}
