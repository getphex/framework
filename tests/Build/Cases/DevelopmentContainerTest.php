<?php

/**
 * Compares interpreted service lifetimes, routing and failure behavior with generated
 * containers on PHP.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Cases;

use Fiber;
use LogicException;
use Phex\Build\Analysis\InvalidContainer;
use Phex\Build\Compiler\BuildConfig;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Container\Routing\EndpointReference;
use Phex\Examples\CompiledContainer\Connection;
use Phex\Examples\CompiledContainer\Generated\ApplicationContainer;
use Phex\Examples\CompiledContainer\MemoryUserRepository;
use Phex\Examples\CompiledContainer\RequestSession;
use Phex\Examples\CompiledContainer\UserEndpoint;
use Phex\Examples\CompiledContainer\UserRepository;
use Phex\Examples\CompiledContainer\UserService;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Kernel\Application;
use Phex\Kernel\Scope\RequestScope;
use Phex\Runtime\Development\DevelopmentContainer;
use Phex\Tests\Build\Fixtures\BadFactories;
use Phex\Tests\Build\Fixtures\Defaults;
use Phex\Tests\Build\Fixtures\Missing;
use Phex\Tests\Compatibility\Fixtures\Container\ApplicationConstructionFailure;
use Phex\Tests\Compatibility\Fixtures\Container\RequestConstructionFailure;
use Phex\Tests\Compatibility\Fixtures\Container\YieldingService;
use Phex\Tests\Compatibility\Fixtures\RoutingCases;
use Phex\Tests\Generated\RoutingContainer;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;

/** Host-only: the interpreted backend must never enter the native compatibility suite. */
final class DevelopmentContainerTest extends TestCase
{
    #[DataProviderExternal(ContainerCompilerTest::class, 'invalidGraphs')]
    public function testDevelopmentRejectsTheSameInvalidGraphs(Container $definition, string $message): void
    {
        $this->expectException(InvalidContainer::class);
        $this->expectExceptionMessage($message);
        new DevelopmentContainer($definition);
    }

    #[DataProviderExternal(ContainerCompilerTest::class, 'invalidFactories')]
    public function testDevelopmentRejectsTheSameInvalidFactories(string $method): void
    {
        $this->expectException(InvalidContainer::class);
        new DevelopmentContainer(new Container([
            Service::factory(Missing::class, Lifetime::Request, BadFactories::class, $method),
        ]));
    }

    public function testLifetimesAndOwnershipMatchTheCompiledContainer(): void
    {
        $container = $this->container();
        $alice = $this->scope('alice');
        $bob = $this->scope('bob');
        try {
            $first = $container->request($alice);
            $second = $container->request($bob);
            $this->assertSame($first, $container->request($alice));
            $this->assertNotSame($first, $second);
            $this->assertSame($container->get(UserRepository::class), $first->get(UserService::class)->repository);
            $this->assertSame($first->get(UserRepository::class), $second->get(UserRepository::class));
            $this->assertSame($first->get(UserService::class), $first->get(UserService::class));
            $this->assertNotSame($first->get(UserService::class), $second->get(UserService::class));
            $this->assertNotSame($first->get(UserEndpoint::class), $first->get(UserEndpoint::class));
            $this->assertSame($first->get(UserService::class), $first->get(UserEndpoint::class)->users);
            $this->assertSame('alice', $first->get(RequestSession::class)->id);
            $this->assertSame('bob', $second->get(RequestSession::class)->id);
            $session = $first->get(RequestSession::class);
            $connection = $first->get(Connection::class);
            $other = $first->get(Connection::class);
            $this->assertNotSame($connection, $other);
            $repository = $container->get(UserRepository::class);
            $this->assertInstanceOf(MemoryUserRepository::class, $repository);
            $alice->close();
            $alice->close();
            $this->assertSame(1, $session->closes);
            $this->assertSame(1, $connection->closes);
            $this->assertSame(1, $other->closes);
            $this->assertSame(0, $repository->closes);
            $this->assertSame(0, $repository->connection->closes);
        } finally {
            $alice->close();
            $bob->close();
            $container->close();
            $container->close();
        }
        $this->assertSame(1, $repository->closes);
        $this->assertSame(1, $repository->connection->closes);
    }

    public function testSeparateContainersHaveSeparateContextsInOneScope(): void
    {
        $first = $this->container();
        $second = $this->container();
        $scope = $this->scope('shared');
        try {
            $a = $first->request($scope);
            $b = $second->request($scope);
            $this->assertNotSame($a, $b);
            $this->assertNotSame($a->get(RequestSession::class), $b->get(RequestSession::class));
            $this->assertNotSame($a->get(UserRepository::class), $b->get(UserRepository::class));
        } finally {
            $scope->close();
            $first->close();
            $second->close();
        }
    }

    public function testMiddlewareAndRoutingResponsesMatchGeneratedPhp(): void
    {
        $development = $this->container();
        $compiled = new ApplicationContainer();
        try {
            $a = new Application($development);
            $b = new Application($compiled);
            foreach (['alice', 'bob', 'anonymous'] as $id) {
                foreach ([['GET', '/users/7'], ['GET', '/missing'], ['POST', '/users/7'], ['HEAD', '/users/7'], ['GET', '/users/nope'], ['GET', '/users/%ZZ']] as [$method, $uri]) {
                    $request = new Request($method, $uri, new Headers(['X-Request-Id' => [$id]]));
                    $expected = $b->handle($request);
                    $actual = $a->handle($request);
                    $this->assertEquals($expected, $actual);
                }
            }
        } finally {
            $development->close();
            $compiled->close();
        }
    }

    public function testTheDefaultRouterMatchesCompiledRoutingAcrossMethodsAndPaths(): void
    {
        $development = new DevelopmentContainer($this->definition('tests/Build/compiled-routing.php'));
        $compiled = new RoutingContainer();
        try {
            $a = new Application($development);
            $b = new Application($compiled);
            foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS', 'get', '2', '10', 'TRACE'] as $method) {
                foreach (RoutingCases::targets() as $target) {
                    $request = new Request($method, $target);
                    $this->assertEquals($b->handle($request), $a->handle($request), $method.' '.$target);
                }
            }
        } finally {
            $development->close();
            $compiled->close();
        }
    }

    public function testJsonCreationAndFailuresMatchGeneratedPhp(): void
    {
        $development = $this->container();
        $compiled = new ApplicationContainer();
        try {
            $a = new Application($development);
            $b = new Application($compiled);
            foreach (['{', '{}', '[]', '{"name":42}', '{"name":"Ada"}', '{"name":"Bob"}'] as $body) {
                $request = new Request('POST', '/users', new Headers(['Content-Type' => ['application/json'], 'X-Request-Id' => [$body]]), $body);
                $this->assertEquals($b->handle($request), $a->handle($request));
            }
            $this->assertSame('Ada', $a->handle(new Request('GET', '/users/1'))->body);
            $this->assertSame('Bob', $b->handle(new Request('GET', '/users/2'))->body);
        } finally {
            $development->close();
            $compiled->close();
        }
    }

    public function testEndpointReferencesUseTheExistingRequestContext(): void
    {
        $container = $this->container();
        $scope = $this->scope('alice');
        try {
            $context = $container->request($scope);
            $endpoint = $context->resolve(new EndpointReference(UserEndpoint::class));
            $this->assertInstanceOf(UserEndpoint::class, $endpoint);
            $this->assertSame($context->get(UserService::class), $endpoint->users);
            $this->assertSame($endpoint, $context->resolve($endpoint));
        } finally {
            $scope->close();
            $container->close();
        }
    }

    public function testLiteralDefaultsRemainData(): void
    {
        $container = new DevelopmentContainer(new Container([Service::constructor(Defaults::class, Lifetime::Application)]));
        try {
            $this->assertEquals(new Defaults(), $container->get(Defaults::class));
        } finally {
            $container->close();
        }
    }

    public function testApplicationConstructionFailureReleasesEarlierResources(): void
    {
        try {
            new DevelopmentContainer($this->definition('tests/Build/failing-application.php'));
        } catch (ApplicationConstructionFailure $failure) {
            $this->assertInstanceOf(MemoryUserRepository::class, $failure->repository);
            $this->assertSame(1, $failure->repository->closes);
            $this->assertSame(1, $failure->repository->connection->closes);

            return;
        }
        $this->fail('The constructor must fail.');
    }

    public function testRequestConstructionFailureIsCleanedUpByTheKernel(): void
    {
        $container = new DevelopmentContainer($this->definition('tests/Build/failing-request.php'));
        try {
            (new Application($container))->handle(new Request('GET', '/'));
        } catch (RequestConstructionFailure $failure) {
            $this->assertSame(1, $failure->session->closes);
            $this->assertSame(1, $failure->connection->closes);

            return;
        } finally {
            $container->close();
        }
        $this->fail('The constructor must fail.');
    }

    public function testClosedScopesRejectOldAndNewContexts(): void
    {
        $container = $this->container();
        $scope = $this->scope('closed');
        $context = $container->request($scope);
        $scope->close();
        try {
            foreach ([fn () => $context->get(RequestSession::class), fn () => $container->request($scope)] as $resolve) {
                try {
                    $resolve();
                    $this->fail('Closed scope must reject resolution.');
                } catch (LogicException $error) {
                    $this->assertStringContainsString('closed', $error->getMessage());
                }
            }
        } finally {
            $container->close();
        }
    }

    public function testApplicationResolutionRejectsRequestStateAndUnknownServices(): void
    {
        $container = $this->container();
        try {
            foreach ([RequestSession::class, Connection::class, Missing::class] as $type) {
                try {
                    $container->get($type);
                    $this->fail('Invalid application resolution must fail.');
                } catch (LogicException $error) {
                    $this->assertNotSame('', $error->getMessage());
                }
            }
        } finally {
            $container->close();
        }
        $this->expectException(LogicException::class);
        $container->get(UserRepository::class);
    }

    public function testInterleavedRequestsKeepTheirOwnCaches(): void
    {
        $container = $this->container();
        $a = $this->scope('alice');
        $b = $this->scope('bob');
        $first = new Fiber(function () use ($container, $a): string {
            $context = $container->request($a);
            $session = $context->get(RequestSession::class);
            Fiber::suspend();
            $this->assertSame($session, $context->get(UserService::class)->session);

            return $session->id;
        });
        $second = new Fiber(fn (): string => $container->request($b)->get(RequestSession::class)->id);
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
        $container = new DevelopmentContainer($this->definition('tests/Build/failing-request.php'));
        $scope = $this->scope('shared');
        $context = $container->request($scope);
        $fiber = new Fiber(fn () => $context->get(YieldingService::class));
        try {
            $fiber->start();
            try {
                $context->get(YieldingService::class);
                $this->fail('Concurrent construction must fail.');
            } catch (LogicException $error) {
                $this->assertSame('Service construction is already in progress.', $error->getMessage());
            }
            $fiber->resume();
            $this->assertSame($fiber->getReturn(), $context->get(YieldingService::class));
        } finally {
            $scope->close();
            $container->close();
        }
    }

    private function container(): DevelopmentContainer
    {
        return new DevelopmentContainer($this->definition('examples/compiled-container/container.php'));
    }

    private function definition(string $path): Container
    {
        $config = require dirname(__DIR__, 3).'/'.$path;
        $this->assertInstanceOf(BuildConfig::class, $config);

        return $config->container;
    }

    private function scope(string $id): RequestScope
    {
        return new RequestScope(new Request('GET', '/users/7', new Headers(['X-Request-Id' => [$id]])));
    }
}
