<?php

/**
 * Loads the compiled-container declarations and HTTP host needed by static web entry
 * points.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/../../src/Contracts/Runtime/RequestReader.php';
require_once __DIR__.'/../../src/Contracts/Runtime/ErrorReporter.php';
require_once __DIR__.'/../../src/Runtime/Logging/bootstrap.php';
require_once __DIR__.'/../../src/Runtime/Logging/SapiLogOutput.php';
require_once __DIR__.'/../../src/Contracts/Runtime/ResponseEmitter.php';
require_once __DIR__.'/../../src/Contracts/Runtime/ResponseOutput.php';
require_once __DIR__.'/../../src/Runtime/Http/InvalidRequest.php';
require_once __DIR__.'/../../src/Runtime/Http/BufferedResponseEmitter.php';
require_once __DIR__.'/../../src/Runtime/Http/SapiHeaders.php';
require_once __DIR__.'/../../src/Runtime/Http/WebApplication.php';
require_once __DIR__.'/../../src/Runtime/Fpm/ServerRequest.php';
require_once __DIR__.'/WebApplication.php';
