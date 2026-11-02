<?php

declare(strict_types=1);

use Phex\Runtime\Development\DevelopmentContainer;
use Phex\Runtime\Fpm\GlobalsRequestReader;
use Phex\Runtime\Fpm\SapiOutput;
use Phex\Runtime\Fpm\SapiErrorReporter;
use Phex\Runtime\Http\WebApplication;
use Phex\Runtime\Http\RandomRequestIdGenerator;

require_once __DIR__.'/../bootstrap/app.php';

$method = ($_SERVER['REQUEST_METHOD'] ?? null) === 'HEAD' ? 'HEAD' : 'GET';
WebApplication::serve(new GlobalsRequestReader(), new SapiOutput(),
    fn (): DevelopmentContainer => DevelopmentContainer::fromFile(__DIR__.'/../config/services.php'), new SapiErrorReporter(), $method, new RandomRequestIdGenerator());
