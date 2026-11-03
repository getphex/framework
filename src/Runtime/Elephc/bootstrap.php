<?php

/**
 * Loads request-lifecycle native HTTP adapters through explicit includes without
 * development runtime dependencies.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

require_once __DIR__.'/../shared.php';
require_once __DIR__.'/NativeRequestReader.php';
require_once __DIR__.'/NativeOutput.php';
require_once __DIR__.'/../Logging/SapiLogOutput.php';
require_once __DIR__.'/NativeErrorReporter.php';
