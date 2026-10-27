<?php

declare(strict_types=1);

use Phex\Build\Compiler\BuildConfig;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\EndpointRoute;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Contracts\Http\Handler;
use Phex\Examples\CompiledContainer\Connection;
use Phex\Examples\CompiledContainer\CreateUserDecoder;
use Phex\Examples\CompiledContainer\CreateUserEndpoint;
use Phex\Examples\CompiledContainer\Factories;
use Phex\Examples\CompiledContainer\RequestSession;
use Phex\Examples\CompiledContainer\UserEndpoint;
use Phex\Examples\CompiledContainer\UserRepository;
use Phex\Examples\CompiledContainer\UserService;

return new BuildConfig(new Container([
    Service::constructor(Connection::class, Lifetime::Transient),
    Service::constructor(CreateUserDecoder::class, Lifetime::Application),
    Service::constructor(CreateUserEndpoint::class, Lifetime::Transient),
    Service::factory(UserRepository::class, Lifetime::Application, Factories::class, 'repository'),
    Service::factory(RequestSession::class, Lifetime::Request, Factories::class, 'session'),
    Service::constructor(UserService::class, Lifetime::Request),
    Service::constructor(UserEndpoint::class, Lifetime::Transient),
    Service::factory(Handler::class, Lifetime::Request, Factories::class, 'handler'),
], [
    'repository' => UserRepository::class,
    'users' => UserService::class,
    'session' => RequestSession::class,
    'endpoint' => UserEndpoint::class,
    'connection' => Connection::class,
], [
    new EndpointRoute('GET', '/users/{id}', UserEndpoint::class),
    new EndpointRoute('POST', '/users', CreateUserEndpoint::class),
], Handler::class),
    'Phex\\Examples\\CompiledContainer\\Generated\\ApplicationContainer',
    __DIR__.'/../../var/generated/example/ApplicationContainer.php',
);
