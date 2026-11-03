<?php

/**
 * Serves the compiled-container example through ElephC's request-lifecycle native web
 * adapters.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Phex\Examples\CompiledContainer\WebApplication;
use Phex\Runtime\Elephc\NativeOutput;
use Phex\Runtime\Elephc\NativeRequestReader;
use Phex\Runtime\Elephc\NativeErrorReporter;

require_once __DIR__.'/http-bootstrap.php';
require_once __DIR__.'/../../src/Runtime/Elephc/NativeRequestReader.php';
require_once __DIR__.'/../../src/Runtime/Elephc/NativeOutput.php';
require_once __DIR__.'/../../src/Runtime/Elephc/NativeErrorReporter.php';

$requestMethod = ($_SERVER['REQUEST_METHOD'] ?? null) === 'HEAD' ? 'HEAD' : 'GET';
WebApplication::serve(new NativeRequestReader(), new NativeOutput(), new NativeErrorReporter(), $requestMethod);
