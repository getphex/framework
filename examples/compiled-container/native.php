<?php

declare(strict_types=1);

use Phex\Examples\CompiledContainer\WebApplication;
use Phex\Runtime\Elephc\NativeOutput;
use Phex\Runtime\Elephc\NativeRequestReader;

require_once __DIR__.'/http-bootstrap.php';
require_once __DIR__.'/../../src/Runtime/Elephc/NativeRequestReader.php';
require_once __DIR__.'/../../src/Runtime/Elephc/NativeOutput.php';

$requestMethod = ($_SERVER['REQUEST_METHOD'] ?? null) === 'HEAD' ? 'HEAD' : 'GET';
WebApplication::serve(new NativeRequestReader(), new NativeOutput(), $requestMethod);
