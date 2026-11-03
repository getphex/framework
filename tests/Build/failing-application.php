<?php

/**
 * Builds an application-construction failure fixture to verify cleanup of services created
 * earlier.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Phex\Build\Compiler\BuildConfig;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Examples\CompiledContainer\Connection;
use Phex\Examples\CompiledContainer\Factories;
use Phex\Examples\CompiledContainer\UserRepository;
use Phex\Tests\Compatibility\Fixtures\Container\BrokenApplication;

return new BuildConfig(new Container([
    Service::constructor(Connection::class, Lifetime::Application),
    Service::factory(UserRepository::class, Lifetime::Application, Factories::class, 'repository'),
    Service::constructor(BrokenApplication::class, Lifetime::Application),
]), 'Phex\\Tests\\Generated\\FailingApplication', __DIR__.'/../../var/generated/tests/FailingApplication.php');
