<?php

/**
 * Verifies logging dependency lifetimes, request-context isolation and strict structured-
 * field validation.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Cases;

use Phex\Build\Analysis\InvalidContainer;
use Phex\Build\Compiler\BuildConfig;
use Phex\Build\Compiler\ContainerCompiler;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Contracts\Logging\Logger;
use Phex\Contracts\Logging\LogOutput;
use Phex\Http\Logging\RequestLogger;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Kernel\Scope\RequestScope;
use Phex\Logging\Level;
use Phex\Logging\Record;
use InvalidArgumentException;
use ReflectionClass;
use stdClass;
use Phex\Runtime\Development\DevelopmentContainer;
use Phex\Tests\Compatibility\Fixtures\Logging\MemoryLogs;
use PHPUnit\Framework\TestCase;

final class LoggingConfigurationTest extends TestCase
{
    public function testDevelopmentKeepsRequestContextsSeparateWithOneSharedOutput(): void
    {
        $container = new DevelopmentContainer($this->config()->container);
        $first = new RequestScope(new Request('GET', '/', new Headers(['X-Request-Id' => ['first']])));
        $second = new RequestScope(new Request('POST', '/', new Headers(['X-Request-Id' => ['second']])));
        try {
            $logs = $container->get(LogOutput::class);
            $this->assertInstanceOf(MemoryLogs::class, $logs);
            $one = $container->request($first)->get(Logger::class);
            $two = $container->request($second)->get(Logger::class);
            $this->assertNotSame($one, $two);
            $two->log(new Record(Level::Info, 'second'));
            $one->log(new Record(Level::Info, 'first'));
            $this->assertSame('second', $logs->record(0)->get('request_id'));
            $this->assertSame('POST', $logs->record(0)->get('method'));
            $this->assertSame('first', $logs->record(1)->get('request_id'));
            $this->assertSame('GET', $logs->record(1)->get('method'));
        } finally {
            $first->close();
            $second->close();
            $container->close();
        }
    }

    public function testAnApplicationLoggerCannotCaptureRequestContext(): void
    {
        $services = [];
        foreach ($this->config()->container->services as $service) {
            $services[] = $service->type === Logger::class
                ? Service::constructor(Logger::class, Lifetime::Application, RequestLogger::class) : $service;
        }
        $this->expectException(InvalidContainer::class);
        $this->expectExceptionMessage('captures request state:');
        (new ContainerCompiler())->compile(new Container($services), 'Test\\Logging');
    }

    public function testUntypedCallersCannotTriggerImplicitObjectSerialization(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReflectionClass(Record::class))->newInstanceArgs([Level::Info, 'event', ['object' => new stdClass()]]);
    }

    public function testUntypedCallersCannotIntroduceNumericFieldNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new ReflectionClass(Record::class))->newInstanceArgs([Level::Info, 'event', [0 => 'value']]);
    }

    private function config(): BuildConfig
    {
        $config = require __DIR__.'/../logging.php';
        $this->assertInstanceOf(BuildConfig::class, $config);

        return $config;
    }
}
