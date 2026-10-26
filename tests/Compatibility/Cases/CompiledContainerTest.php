<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use Fiber;
use LogicException;
use Phex\Container\Routing\EndpointReference;
use Phex\Examples\CompiledContainer\Generated\ApplicationContainer;
use Phex\Examples\CompiledContainer\MemoryUserRepository;
use Phex\Examples\CompiledContainer\UserEndpoint;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Kernel\Application;
use Phex\Kernel\Scope\RequestScope;
use Phex\Tests\Compatibility\Fixtures\Container\ApplicationConstructionFailure;
use Phex\Tests\Compatibility\Fixtures\Container\RequestConstructionFailure;
use Phex\Tests\Generated\FailingApplication;
use Phex\Tests\Generated\FailingRequest;
use PHPUnit\Framework\TestCase;

final class CompiledContainerTest extends TestCase
{
    public function testLifetimesAndTypedEntries(): void
    {
        $container = new ApplicationContainer();
        $firstScope = $this->scope('alice');
        $secondScope = $this->scope('bob');
        try {
            $first = $container->request($firstScope);
            $second = $container->request($secondScope);
            $this->assertSame($first, $container->request($firstScope));
            $this->assertNotSame($first, $second);
            $this->assertSame($container->repository(), $first->users()->repository);
            $this->assertSame($first->users()->repository, $second->users()->repository);
            $this->assertSame($first->users(), $first->users());
            $this->assertNotSame($first->users(), $second->users());
            $this->assertSame($first->users(), $first->endpoint()->users);
            $this->assertNotSame($first->endpoint(), $first->endpoint());
            $this->assertSame('alice', $first->session()->id);
            $this->assertSame('bob', $second->session()->id);
        } finally {
            $firstScope->close();
            $secondScope->close();
            $container->close();
        }
    }

    public function testResourcesBelongToTheirEffectiveOwner(): void
    {
        $container = new ApplicationContainer();
        $scope = $this->scope('alice');
        $context = $container->request($scope);
        $repository = $this->repository($container->repository());
        $session = $context->session();
        $first = $context->connection();
        $second = $context->connection();
        $this->assertNotSame($first, $second);
        $scope->close();
        $scope->close();
        $this->assertSame(1, $session->closes);
        $this->assertSame(1, $first->closes);
        $this->assertSame(1, $second->closes);
        $this->assertSame(0, $repository->closes);
        $this->assertSame(0, $repository->connection->closes);
        $container->close();
        $container->close();
        $this->assertSame(1, $repository->closes);
        $this->assertSame(1, $repository->connection->closes);
    }

    public function testSeparateContainersHaveSeparateContextsEvenInOneScope(): void
    {
        $first = new ApplicationContainer();
        $second = new ApplicationContainer();
        $scope = $this->scope('shared');
        $a = $first->request($scope);
        $b = $second->request($scope);
        $this->assertNotSame($a, $b);
        $this->assertNotSame($a->session(), $b->session());
        $this->assertNotSame($a->repository(), $b->repository());
        $scope->close();
        $first->close();
        $second->close();
    }

    public function testSharedRoutesResolveTheCurrentRequestServices(): void
    {
        $container = new ApplicationContainer();
        $matcher = $container->matcher();
        $application = new Application($container);
        $first = $application->handle($this->scope('alice')->request);
        $second = $application->handle($this->scope('bob')->request);
        $this->assertSame('user-7', $first->body);
        $this->assertSame(['alice'], $first->headers->values('X-Request-Id'));
        $this->assertSame(['alice'], $first->headers->values('X-Middleware-Id'));
        $this->assertSame(['bob'], $second->headers->values('X-Request-Id'));
        $this->assertSame(['bob'], $second->headers->values('X-Middleware-Id'));
        $this->assertSame(404, $application->handle(new Request('GET', '/missing'))->status);
        $this->assertSame(405, $application->handle(new Request('POST', '/users/7'))->status);
        $this->assertSame(400, $application->handle(new Request('GET', '/users/nope'))->status);
        $this->assertSame($matcher, $container->matcher());
        $container->close();
    }

    public function testEndpointReferencesResolveThroughTheExistingContext(): void
    {
        $container = new ApplicationContainer();
        $scope = $this->scope('alice');
        $context = $container->request($scope);
        $endpoint = $this->endpoint($context->resolve(new EndpointReference(UserEndpoint::class)));
        $this->assertSame($context->users(), $endpoint->users);
        $this->assertSame($endpoint, $context->resolve($endpoint));
        $scope->close();
        $container->close();
    }

    public function testApplicationConstructorFailureReleasesEarlierServices(): void
    {
        try {
            new FailingApplication();
        } catch (ApplicationConstructionFailure $failure) {
            $repository = $this->repository($failure->repository);
            $this->assertSame(1, $repository->closes);
            $this->assertSame(1, $repository->connection->closes);

            return;
        }
        $this->fail('The constructor must fail.');
    }

    public function testRequestConstructorFailureIsCleanedUpByTheKernel(): void
    {
        $container = new FailingRequest();
        $application = new Application($container);
        try {
            $application->handle(new Request('GET', '/'));
        } catch (RequestConstructionFailure $failure) {
            $this->assertSame(1, $failure->session->closes);
            $this->assertSame(1, $failure->connection->closes);

            return;
        } finally {
            $container->close();
        }
        $this->fail('The constructor must fail.');
    }

    public function testClosedRequestContainersRejectResolution(): void
    {
        $container = new ApplicationContainer();
        $scope = $this->scope('closed');
        $context = $container->request($scope);
        $scope->close();
        try {
            $this->expectException(LogicException::class);
            $context->session();
        } finally {
            $container->close();
        }
    }

    public function testClosedScopesCannotCreateNewContexts(): void
    {
        $container = new ApplicationContainer();
        $scope = $this->scope('closed');
        $scope->close();
        try {
            $this->expectException(LogicException::class);
            $container->request($scope);
        } finally {
            $container->close();
        }
    }

    public function testClosedApplicationContainersRejectResolution(): void
    {
        $container = new ApplicationContainer();
        $container->close();
        $this->expectException(LogicException::class);
        $container->repository();
    }

    public function testInterleavedRequestsKeepTheirOwnCaches(): void
    {
        $container = new ApplicationContainer();
        $a = $this->scope('alice');
        $b = $this->scope('bob');
        $first = new Fiber(function () use ($container, $a): string {
            $context = $container->request($a);
            $session = $context->session();
            Fiber::suspend();
            $this->assertSame($session, $context->users()->session);

            return $session->id;
        });
        $second = new Fiber(function () use ($container, $b): string {
            return $container->request($b)->session()->id;
        });
        try {
            $first->start();
            $second->start();
            $first->resume();
            $this->assertSame('alice', $first->getReturn());
            $this->assertSame('bob', $second->getReturn());
        } finally {
            $a->close();
            $b->close();
            $container->close();
        }
    }

    public function testConcurrentConstructionInOneScopeFailsWithoutDuplicatingTheService(): void
    {
        $container = new FailingRequest();
        $scope = $this->scope('shared');
        $context = $container->request($scope);
        $fiber = new Fiber(fn () => $context->yielding());
        try {
            $fiber->start();
            try {
                $context->yielding();
                $this->fail('Concurrent construction must be rejected.');
            } catch (LogicException $error) {
                $this->assertSame('Service construction is already in progress.', $error->getMessage());
            }
            $fiber->resume();
            $this->assertSame($fiber->getReturn(), $context->yielding());
        } finally {
            $scope->close();
            $container->close();
        }
    }

    private function scope(string $id): RequestScope
    {
        return new RequestScope(new Request('GET', '/users/7', new Headers(['X-Request-Id' => [$id]])));
    }

    private function repository(mixed $value): MemoryUserRepository
    {
        if (!$value instanceof MemoryUserRepository) {
            throw new LogicException('Expected the concrete repository.');
        }

        return $value;
    }

    private function endpoint(mixed $value): UserEndpoint
    {
        if (!$value instanceof UserEndpoint) {
            throw new LogicException('Expected the concrete endpoint.');
        }

        return $value;
    }
}
