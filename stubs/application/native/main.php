<?php

declare(strict_types=1);

use App\Generated\ApplicationContainer;
use Phex\Runtime\Elephc\NativeOutput;
use Phex\Runtime\Elephc\NativeErrorReporter;
use Phex\Runtime\Elephc\NativeRequestReader;
use Phex\Runtime\Http\WebApplication;
use Phex\Runtime\Http\RandomRequestIdGenerator;

require_once __DIR__.'/../var/generated/native-bootstrap.php';

$method = ($_SERVER['REQUEST_METHOD'] ?? null) === 'HEAD' ? 'HEAD' : 'GET';
WebApplication::serve(new NativeRequestReader(), new NativeOutput(), fn (): ApplicationContainer => new ApplicationContainer(), new NativeErrorReporter(), $method, new RandomRequestIdGenerator());
