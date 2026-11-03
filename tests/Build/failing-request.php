<?php

/**
 * Builds a request-construction failure fixture to verify kernel-owned partial-
 * construction cleanup.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Phex\Contracts\Http\Handler;
use Phex\Tests\Compatibility\Fixtures\Container\BrokenFactory;
use Phex\Tests\Compatibility\Fixtures\Container\YieldingService;
use Phex\Build\Compiler\BuildConfig;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Examples\CompiledContainer\Connection;
use Phex\Examples\CompiledContainer\Factories;
use Phex\Examples\CompiledContainer\RequestSession;
use Phex\Tests\Compatibility\Fixtures\Container\BrokenRequest;

return new BuildConfig(new Container([
    Service::constructor(Connection::class, Lifetime::Transient),
    Service::factory(RequestSession::class, Lifetime::Request, Factories::class, 'session'),
    Service::constructor(BrokenRequest::class, Lifetime::Request),
    Service::factory(Handler::class, Lifetime::Request, BrokenFactory::class, 'handler'),
    Service::factory(YieldingService::class, Lifetime::Request, YieldingService::class, 'create'),
], ['broken' => BrokenRequest::class, 'session' => RequestSession::class, 'yielding' => YieldingService::class], handler: Handler::class),
    'Phex\\Tests\\Generated\\FailingRequest', __DIR__.'/../../var/generated/tests/FailingRequest.php');
