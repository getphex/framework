<?php

/**
 * Loads logging declarations and the SAPI log adapter without bringing development tooling
 * into native builds.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

// Declaration-only logging graph, also usable by native CLI hosts with a custom output.
require_once __DIR__.'/../../Logging/Level.php';
require_once __DIR__.'/../../Logging/Record.php';
require_once __DIR__.'/../../Contracts/Logging/Logger.php';
require_once __DIR__.'/../../Contracts/Logging/LogOutput.php';
require_once __DIR__.'/../../Contracts/Runtime/ErrorReporter.php';
require_once __DIR__.'/../../Http/Logging/RequestId.php';
require_once __DIR__.'/../../Contracts/Runtime/RequestIdGenerator.php';
require_once __DIR__.'/../../Logging/JsonLogger.php';
require_once __DIR__.'/../../Logging/LoggerErrorReporter.php';
require_once __DIR__.'/../../Http/Logging/RequestLogContext.php';
require_once __DIR__.'/../../Http/Logging/RequestLogger.php';
require_once __DIR__.'/../../Http/Logging/RequestLoggingMiddleware.php';
require_once __DIR__.'/../Http/RandomRequestIdGenerator.php';
