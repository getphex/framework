<?php

declare(strict_types=1);

use Phex\Examples\Database\Generated\ApplicationContainer;
use Phex\Runtime\Fpm\GlobalsRequestReader;
use Phex\Runtime\Fpm\SapiErrorReporter;
use Phex\Runtime\Fpm\SapiOutput;
use Phex\Runtime\Http\RandomRequestIdGenerator;
use Phex\Runtime\Http\WebApplication;

require_once __DIR__.'/../../../src/Database/bootstrap.php';
require_once __DIR__.'/../var/generated/php-bootstrap.php';

$method = ($_SERVER['REQUEST_METHOD'] ?? null) === 'HEAD' ? 'HEAD' : 'GET';
WebApplication::serve(new GlobalsRequestReader(), new SapiOutput(), fn (): ApplicationContainer => new ApplicationContainer(),
    new SapiErrorReporter(), $method, new RandomRequestIdGenerator());
