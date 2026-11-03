<?php

/**
 * Declares explicit service bindings, lifetimes, logging and routes for interpreted and
 * compiled execution.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use App\Config\ApplicationSettings;
use App\Config\SettingsFactory;
use App\Http\Action\GreetAction;
use App\Http\Action\HomeAction;
use App\Http\Action\SearchAction;
use App\Http\Handlers;
use App\Input\GreetingDecoder;
use App\Input\SearchDecoder;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\DefinitionLoader;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Contracts\Http\Handler;
use Phex\Contracts\Config\EnvironmentReader;
use Phex\Contracts\Logging\Logger;
use Phex\Contracts\Logging\LogOutput;
use Phex\Http\Logging\RequestLogContext;
use Phex\Http\Logging\RequestLogger;
use Phex\Http\Logging\RequestLoggingMiddleware;
use Phex\Logging\JsonLogger;
use Phex\Runtime\Logging\SapiLogOutput;
use Phex\Config\Environment;
use Phex\Runtime\Environment\ProcessEnvironment;

return new Container([
    Service::constructor(LogOutput::class, Lifetime::Application, SapiLogOutput::class),
    Service::factory(JsonLogger::class, Lifetime::Application, JsonLogger::class, 'create'),
    Service::factory(RequestLogContext::class, Lifetime::Request, RequestLogContext::class, 'create'),
    Service::constructor(Logger::class, Lifetime::Request, RequestLogger::class),
    Service::constructor(RequestLoggingMiddleware::class, Lifetime::Request),
    Service::constructor(EnvironmentReader::class, Lifetime::Application, ProcessEnvironment::class),
    Service::constructor(Environment::class, Lifetime::Application),
    Service::factory(ApplicationSettings::class, Lifetime::Application, SettingsFactory::class, 'create'),
    Service::constructor(HomeAction::class, Lifetime::Transient),
    Service::constructor(GreetAction::class, Lifetime::Transient),
    Service::constructor(GreetingDecoder::class, Lifetime::Application),
    Service::constructor(SearchAction::class, Lifetime::Transient),
    Service::constructor(SearchDecoder::class, Lifetime::Application),
    Service::factory(Handler::class, Lifetime::Request, Handlers::class, 'create'),
], routes: DefinitionLoader::routes(__DIR__.'/../routes/web.php'), handler: Handler::class);
