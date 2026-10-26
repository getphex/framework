<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use Error;
use LogicException;
use Phex\Http\Message\Request;
use Phex\Kernel\Scope\CleanupFailed;
use Phex\Kernel\Scope\RequestScope;
use Phex\Tests\Compatibility\Fixtures\ScopeCallbackResource;
use Phex\Tests\Compatibility\Fixtures\ScopeLog;
use Phex\Tests\Compatibility\Fixtures\TrackedResource;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RequestScopeTest extends TestCase
{
    public function testAnEmptyScopeClosesIdempotently(): void
    {
        $request = new Request('GET', '/');
        $scope = new RequestScope($request);

        $this->assertSame($request, $scope->request);
        $this->assertFalse($scope->isClosed());
        $scope->close();
        $scope->close();
        $this->assertTrue($scope->isClosed());
    }

    public function testOwnershipPreservesTheConcreteResourceType(): void
    {
        $scope = new RequestScope(new Request('GET', '/'));
        $log = new ScopeLog();
        $resource = $scope->own(new TrackedResource('typed', $log));

        $this->assertSame('typed', $resource->name);
        $this->assertSame(0, $resource->closes);
        $scope->close();
        $this->assertSame(1, $resource->closes);
    }

    public function testResourcesCloseInReverseRegistrationOrder(): void
    {
        $scope = new RequestScope(new Request('GET', '/'));
        $log = new ScopeLog();
        $scope->own(new TrackedResource('first', $log));
        $scope->own(new TrackedResource('second', $log));
        $scope->own(new TrackedResource('third', $log));
        $scope->close();

        $this->assertSame(['third:close', 'second:close', 'first:close'], $log->events);
    }

    public function testOwningTheSameObjectTwiceClosesItOnce(): void
    {
        $scope = new RequestScope(new Request('GET', '/'));
        $log = new ScopeLog();
        $resource = new TrackedResource('shared', $log);

        $this->assertSame($resource, $scope->own($resource));
        $this->assertSame($resource, $scope->own($resource));
        $scope->close();
        $scope->close();
        $this->assertSame(1, $resource->closes);
    }

    public function testDistinctObjectsWithEqualPropertiesAreBothClosed(): void
    {
        $scope = new RequestScope(new Request('GET', '/'));
        $log = new ScopeLog();
        $first = $scope->own(new TrackedResource('same', $log));
        $second = $scope->own(new TrackedResource('same', $log));
        $scope->close();

        $this->assertNotSame($first, $second);
        $this->assertSame(1, $first->closes);
        $this->assertSame(1, $second->closes);
    }

    public function testClosedScopesRejectNewOwnership(): void
    {
        $scope = new RequestScope(new Request('GET', '/'));
        $scope->close();
        $this->expectException(LogicException::class);
        $scope->own(new TrackedResource('late', new ScopeLog()));
    }

    public function testEveryResourceIsAttemptedWhenCleanupFails(): void
    {
        $scope = new RequestScope(new Request('GET', '/'));
        $log = new ScopeLog();
        $firstFailure = new RuntimeException('first failed');
        $lastFailure = new Error('last failed');
        $first = $scope->own(new TrackedResource('first', $log, $firstFailure));
        $middle = $scope->own(new TrackedResource('middle', $log));
        $last = $scope->own(new TrackedResource('last', $log, $lastFailure));

        try {
            $scope->close();
        } catch (CleanupFailed $error) {
            $this->assertSame([$lastFailure, $firstFailure], $error->failures());
            $this->assertSame($lastFailure, $error->getPrevious());
            $this->assertNull($error->requestFailure);
            $this->assertSame(['last:close', 'middle:close', 'first:close'], $log->events);
            $this->assertTrue($scope->isClosed());
            $scope->close();
            $this->assertSame(1, $first->closes);
            $this->assertSame(1, $middle->closes);
            $this->assertSame(1, $last->closes);

            return;
        }

        $this->fail('Cleanup errors must be reported.');
    }

    public function testClosingIsSafeDuringAResourceCallback(): void
    {
        $scope = new RequestScope(new Request('GET', '/'));
        $log = new ScopeLog();
        $scope->own(new TrackedResource('first', $log));
        $scope->own(new ScopeCallbackResource($scope, $log));
        $scope->close();

        $this->assertSame(['closed', 'first:close'], $log->events);
    }

    public function testRegistrationDuringCleanupFailsAndOtherResourcesStillClose(): void
    {
        $scope = new RequestScope(new Request('GET', '/'));
        $log = new ScopeLog();
        $first = $scope->own(new TrackedResource('first', $log));
        $scope->own(new ScopeCallbackResource($scope, $log, true));

        try {
            $scope->close();
        } catch (CleanupFailed $error) {
            $this->assertCount(1, $error->failures());
            $this->assertTrue($error->getPrevious() instanceof LogicException);
            $this->assertSame(['closed', 'first:close'], $log->events);
            $this->assertSame(1, $first->closes);

            return;
        }

        $this->fail('Registering resources during cleanup must fail.');
    }

    public function testCleanupFailureHasATypedFirstCause(): void
    {
        $first = new RuntimeException('first');
        $second = new Error('second');
        $error = new CleanupFailed($first, [$second]);

        $this->assertSame($first, $error->firstFailure);
        $this->assertSame($first, $error->getPrevious());
        $this->assertSame([$first, $second], $error->failures());
    }
}
