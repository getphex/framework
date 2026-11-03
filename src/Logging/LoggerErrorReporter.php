<?php

/**
 * Maps unexpected runtime exceptions to explicit structured log fields without serializing
 * exception objects.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Logging;

use Phex\Contracts\Logging\Logger;
use Phex\Contracts\Runtime\ErrorReporter;
use Throwable;

final readonly class LoggerErrorReporter implements ErrorReporter
{
    public function __construct(private Logger $logger) {}

    public function report(Throwable $error, array $context = []): void
    {
        $context['exception_type'] = get_class($error);
        $this->logger->log(new Record(Level::Error, $error->getMessage(), $context));
    }
}
