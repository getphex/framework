<?php

declare(strict_types=1);

use Phex\Build\Compiler\BuildConfig;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Contracts\Http\Handler;
use Phex\Contracts\Logging\Logger;
use Phex\Contracts\Logging\LogOutput;
use Phex\Http\Logging\RequestLogContext;
use Phex\Http\Logging\RequestLogger;
use Phex\Logging\JsonLogger;
use Phex\Tests\Compatibility\Fixtures\Logging\LogHandler;
use Phex\Tests\Compatibility\Fixtures\Logging\MemoryLogs;

return new BuildConfig(new Container([
    Service::constructor(LogOutput::class, Lifetime::Application, MemoryLogs::class),
    Service::factory(JsonLogger::class, Lifetime::Application, JsonLogger::class, 'create'),
    Service::factory(RequestLogContext::class, Lifetime::Request, RequestLogContext::class, 'create'),
    Service::constructor(Logger::class, Lifetime::Request, RequestLogger::class),
    Service::constructor(Handler::class, Lifetime::Request, LogHandler::class),
], ['logs' => LogOutput::class, 'logger' => Logger::class, 'context' => RequestLogContext::class], handler: Handler::class),
    'Phex\\Tests\\Generated\\LoggingContainer',
    __DIR__.'/../../var/generated/tests/LoggingContainer.php',
);
