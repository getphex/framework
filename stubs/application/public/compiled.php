<?php

/**
 * Serves PHP SAPI requests through the generated application bootstrap and container.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use App\Generated\ApplicationContainer;
use Phex\Runtime\Fpm\GlobalsRequestReader;
use Phex\Runtime\Fpm\SapiOutput;
use Phex\Runtime\Fpm\SapiErrorReporter;
use Phex\Runtime\Http\WebApplication;
use Phex\Runtime\Http\RandomRequestIdGenerator;

require_once __DIR__.'/../var/generated/php-bootstrap.php';

$method = ($_SERVER['REQUEST_METHOD'] ?? null) === 'HEAD' ? 'HEAD' : 'GET';
WebApplication::serve(new GlobalsRequestReader(), new SapiOutput(), fn (): ApplicationContainer => new ApplicationContainer(), new SapiErrorReporter(), $method, new RandomRequestIdGenerator());
