<?php

/**
 * Loads shared framework declarations and PHP SAPI transport adapters for generated PHP
 * entry points.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

require_once __DIR__.'/../shared.php';
require_once __DIR__.'/GlobalsRequestReader.php';
require_once __DIR__.'/SapiOutput.php';
require_once __DIR__.'/../Logging/SapiLogOutput.php';
require_once __DIR__.'/SapiErrorReporter.php';
