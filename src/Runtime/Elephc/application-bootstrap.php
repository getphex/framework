<?php

/**
 * Loads native application-lifecycle adapter declarations and their shared framework
 * dependencies.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

// ElephC supplies Elephc\Web declarations only in application lifecycle builds.
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/ApplicationRequestReader.php';
require_once __DIR__.'/NativeApplication.php';
