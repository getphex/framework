<?php

/**
 * Binds application-owned PDO connections, request-owned sessions and the SQLite example's
 * HTTP dependencies.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Phex\Config\Environment;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\DefinitionLoader;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Contracts\Config\EnvironmentReader;
use Phex\Contracts\Database\Connection;
use Phex\Contracts\Http\Handler;
use Phex\Contracts\Logging\Logger;
use Phex\Contracts\Logging\LogOutput;
use Phex\Database\ConnectionConfig;
use Phex\Database\PDOConnection;
use Phex\Database\Session;
use Phex\Examples\Database\DatabaseFactory;
use Phex\Examples\Database\Handlers;
use Phex\Examples\Database\UserEndpoint;
use Phex\Examples\Database\UserMapper;
use Phex\Examples\Database\UserRepository;
use Phex\Examples\Database\UsersEndpoint;
use Phex\Http\Logging\RequestLogContext;
use Phex\Http\Logging\RequestLogger;
use Phex\Http\Logging\RequestLoggingMiddleware;
use Phex\Logging\JsonLogger;
use Phex\Runtime\Environment\ProcessEnvironment;
use Phex\Runtime\Logging\SapiLogOutput;

return new Container([
    Service::constructor(EnvironmentReader::class, Lifetime::Application, ProcessEnvironment::class),
    Service::constructor(Environment::class, Lifetime::Application),
    Service::factory(ConnectionConfig::class, Lifetime::Application, DatabaseFactory::class, 'configuration'),
    Service::factory(Connection::class, Lifetime::Application, PDOConnection::class, 'connect'),
    Service::constructor(Session::class, Lifetime::Request),
    Service::constructor(UserMapper::class, Lifetime::Application),
    Service::constructor(UserRepository::class, Lifetime::Request),
    Service::constructor(UsersEndpoint::class, Lifetime::Transient),
    Service::constructor(UserEndpoint::class, Lifetime::Transient),
    Service::constructor(LogOutput::class, Lifetime::Application, SapiLogOutput::class),
    Service::factory(JsonLogger::class, Lifetime::Application, JsonLogger::class, 'create'),
    Service::factory(RequestLogContext::class, Lifetime::Request, RequestLogContext::class, 'create'),
    Service::constructor(Logger::class, Lifetime::Request, RequestLogger::class),
    Service::constructor(RequestLoggingMiddleware::class, Lifetime::Request),
    Service::factory(Handler::class, Lifetime::Request, Handlers::class, 'create'),
], routes: DefinitionLoader::routes(__DIR__.'/../routes/web.php'), handler: Handler::class);
