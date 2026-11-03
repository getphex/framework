<?php

/**
 * Hosts the compiled-container example through shared HTTP input, output and error-
 * reporting adapters.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

use Phex\Contracts\Runtime\RequestReader;
use Phex\Contracts\Runtime\ResponseOutput;
use Phex\Contracts\Runtime\ErrorReporter;
use Phex\Examples\CompiledContainer\Generated\ApplicationContainer;
use Phex\Runtime\Http\WebApplication as WebHost;

final class WebApplication
{
    /**
     * @template TReader of RequestReader
     * @template TOutput of ResponseOutput
     * @template TReporter of ErrorReporter
     * @param TReader $reader
     * @param TOutput $output
     * @param TReporter $errors
     */
    public static function serve(RequestReader $reader, ResponseOutput $output, ErrorReporter $errors, string $requestMethod): void
    {
        WebHost::serve($reader, $output, fn (): ApplicationContainer => new ApplicationContainer(), $errors, $requestMethod);
    }
}
