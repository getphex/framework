<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use Error;
use Fiber;
use LogicException;
use Phex\Examples\JsonApi\JsonApi;
use Phex\Examples\RequestLifecycle\LifecycleFactory;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Kernel\Application;
use Phex\Kernel\Scope\CleanupFailed;
use Phex\Kernel\Scope\RequestScope;
use Phex\Tests\Compatibility\Fixtures\ScopeFactory;
use Phex\Tests\Compatibility\Fixtures\ScopeLog;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class ApplicationTest extends TestCase
{
    public function testApplicationClosesResourcesAfterTheHandlerReturns(): void
    {
        $log = new ScopeLog();
        $factory = new ScopeFactory($log);
        $application = new Application($factory);
        $request = new Request('GET', '/', body: 'alice');
        $response = $application->handle($request);

        $this->assertSame('alice', $response->body);
        $this->assertSame(['alice'], $response->headers->values('X-Resource'));
        $this->assertSame(['alice:create', 'alice:handle', 'alice:close'], $log->events);
        $this->assertSame($request, $factory->scope()->request);
        $this->assertTrue($factory->scope()->isClosed());
        $this->assertSame(1, $factory->resource()->closes);
    }

    public function testFactoryFailuresStillReleaseRegisteredResources(): void
    {
        $failure = new RuntimeException('factory failed');
        $log = new ScopeLog();
        $factory = new ScopeFactory($log, factoryFailure: $failure);
        $application = new Application($factory);
        $caught = null;

        try {
            $application->handle(new Request('GET', '/', body: 'alice'));
        } catch (Throwable $error) {
            $caught = $error;
        }

        $this->assertSame($failure, $caught);
        $this->assertSame(['alice:create', 'alice:close'], $log->events);
        $this->assertTrue($factory->scope()->isClosed());
        $this->assertSame(1, $factory->resource()->closes);
    }

    public function testHandlerErrorsArePreservedWhenCleanupSucceeds(): void
    {
        $failure = new Error('handler failed');
        $log = new ScopeLog();
        $factory = new ScopeFactory($log, handlerFailure: $failure);
        $application = new Application($factory);
        $caught = null;

        try {
            $application->handle(new Request('GET', '/', body: 'alice'));
        } catch (Throwable $error) {
            $caught = $error;
        }

        $this->assertSame($failure, $caught);
        $this->assertSame(['alice:create', 'alice:handle', 'alice:close'], $log->events);
        $this->assertTrue($factory->scope()->isClosed());
    }

    public function testCleanupFailuresAfterASuccessfulRequestAreReported(): void
    {
        $cleanup = new RuntimeException('cleanup failed');
        $factory = new ScopeFactory(new ScopeLog(), cleanupFailure: $cleanup);
        $application = new Application($factory);

        try {
            $application->handle(new Request('GET', '/', body: 'alice'));
        } catch (CleanupFailed $error) {
            $this->assertNull($error->requestFailure);
            $this->assertSame($cleanup, $error->getPrevious());
            $this->assertSame([$cleanup], $error->failures());
            $this->assertTrue($factory->scope()->isClosed());

            return;
        }

        $this->fail('Cleanup must complete before the response is returned.');
    }

    public function testRequestAndCleanupFailuresAreBothPreserved(): void
    {
        $requestFailure = new RuntimeException('request failed');
        $cleanupFailure = new Error('cleanup failed');
        $factory = new ScopeFactory(new ScopeLog(), handlerFailure: $requestFailure, cleanupFailure: $cleanupFailure);
        $application = new Application($factory);

        try {
            $application->handle(new Request('GET', '/', body: 'alice'));
        } catch (CleanupFailed $error) {
            $this->assertSame($requestFailure, $error->requestFailure);
            $this->assertSame($requestFailure, $error->getPrevious());
            $this->assertSame([$cleanupFailure], $error->failures());
            $this->assertSame(1, $factory->resource()->closes);

            return;
        }

        $this->fail('Request and cleanup failures must both be retained.');
    }

    public function testFactoryAndCleanupFailuresAreBothPreserved(): void
    {
        $requestFailure = new RuntimeException('factory failed');
        $cleanupFailure = new RuntimeException('cleanup failed');
        $factory = new ScopeFactory(new ScopeLog(), factoryFailure: $requestFailure, cleanupFailure: $cleanupFailure);
        $application = new Application($factory);

        try {
            $application->handle(new Request('GET', '/', body: 'alice'));
        } catch (CleanupFailed $error) {
            $this->assertSame($requestFailure, $error->getPrevious());
            $this->assertSame([$cleanupFailure], $error->failures());
            $this->assertTrue($factory->scope()->isClosed());

            return;
        }

        $this->fail('Factory failures must not skip resource cleanup.');
    }

    public function testSequentialRequestsHaveDistinctScopesAndResources(): void
    {
        $log = new ScopeLog();
        $factory = new ScopeFactory($log);
        $application = new Application($factory);
        $first = $application->handle(new Request('GET', '/', body: 'alice'));
        $firstScope = $factory->scope();
        $firstResource = $factory->resource();
        $second = $application->handle(new Request('GET', '/', body: 'bob'));

        $this->assertSame('alice', $first->body);
        $this->assertSame('bob', $second->body);
        $this->assertNotSame($firstScope, $factory->scope());
        $this->assertNotSame($firstResource, $factory->resource());
        $this->assertSame(1, $firstResource->closes);
        $this->assertSame(1, $factory->resource()->closes);
    }

    public function testInterleavedRequestsKeepTheirScopesUntilCompletion(): void
    {
        $factory = new ScopeFactory(new ScopeLog(), suspend: true);
        $application = new Application($factory);
        $first = new Fiber(fn () => $application->handle(new Request('GET', '/', body: 'alice')));
        $second = new Fiber(fn () => $application->handle(new Request('GET', '/', body: 'bob')));

        $this->assertSame('alice', $first->start());
        $firstScope = $factory->scope();
        $firstResource = $factory->resource();
        $this->assertSame('bob', $second->start());
        $secondScope = $factory->scope();
        $secondResource = $factory->resource();
        $this->assertNotSame($firstScope, $secondScope);
        $this->assertFalse($firstScope->isClosed());
        $this->assertFalse($secondScope->isClosed());
        $second->resume();
        $this->assertTrue($secondScope->isClosed());
        $this->assertFalse($firstScope->isClosed());
        $this->assertSame(1, $secondResource->closes);
        $this->assertSame(0, $firstResource->closes);
        $first->resume();
        $this->assertTrue($firstScope->isClosed());
        $this->assertSame(1, $firstResource->closes);
        $firstResponse = $first->getReturn();
        $secondResponse = $second->getReturn();
        $this->assertTrue($firstResponse instanceof Response);
        $this->assertTrue($secondResponse instanceof Response);
        $this->assertSame('alice', $firstResponse->body);
        $this->assertSame('bob', $secondResponse->body);
    }

    public function testLifecycleExampleReusesTheRouterWithPerRequestMiddleware(): void
    {
        $application = new Application(new LifecycleFactory(JsonApi::application()));
        $first = $application->handle(new Request('GET', '/users/7', new Headers(['X-Request-Id' => ['alice']])));
        $second = $application->handle(new Request('GET', '/users/42', new Headers(['X-Request-Id' => ['bob']])));

        $this->assertSame('{"id":7}', $first->body);
        $this->assertSame('{"id":42}', $second->body);
        $this->assertSame(['alice'], $first->headers->values('X-Request-Id'));
        $this->assertSame(['bob'], $second->headers->values('X-Request-Id'));
    }

    public function testTheLifecycleExampleCannotUseItsResourceAfterScopeClosure(): void
    {
        $factory = new LifecycleFactory(JsonApi::application());
        $scope = new RequestScope(new Request('GET', '/health'));
        $handler = $factory->create($scope);
        $scope->close();
        $this->expectException(LogicException::class);
        $handler->handle($scope->request);
    }
}
