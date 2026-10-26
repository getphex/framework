<?php

declare(strict_types=1);

use Phex\Examples\CompiledContainer\WebApplication;
use Phex\Runtime\Fpm\GlobalsRequestReader;
use Phex\Runtime\Fpm\SapiOutput;

require_once __DIR__.'/../http-bootstrap.php';
require_once __DIR__.'/../../../src/Runtime/Fpm/GlobalsRequestReader.php';
require_once __DIR__.'/../../../src/Runtime/Fpm/SapiOutput.php';

$requestMethod = ($_SERVER['REQUEST_METHOD'] ?? null) === 'HEAD' ? 'HEAD' : 'GET';

WebApplication::serve(new GlobalsRequestReader(), new SapiOutput(), $requestMethod);
