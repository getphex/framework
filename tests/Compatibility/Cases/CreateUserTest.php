<?php

/**
 * Verifies typed user creation, rejected JSON recovery and scoped resource cleanup through
 * the example application.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use LogicException;
use Phex\Examples\CompiledContainer\Generated\ApplicationContainer;
use Phex\Examples\CompiledContainer\MemoryUserRepository;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Kernel\Application;
use Phex\Kernel\Scope\RequestScope;
use PHPUnit\Framework\TestCase;

final class CreateUserTest extends TestCase
{
    public function testCreationUsesScopedServicesAndSharedRepositoryState(): void
    {
        $container = new ApplicationContainer();
        $application = new Application($container);
        try {
            $first = $application->handle($this->request('{"name":"  Ada  "}', 'alice'));
            $second = $application->handle($this->request('{"name":"Bob"}', 'bob'));
            $this->assertSame(201, $first->status);
            $this->assertSame('{"id":1,"name":"Ada"}', $first->body);
            $this->assertSame(['/users/1'], $first->headers->values('Location'));
            $this->assertSame(['application/json'], $first->headers->values('Content-Type'));
            $this->assertSame(['alice'], $first->headers->values('X-Request-Id'));
            $this->assertSame(['alice'], $first->headers->values('X-Middleware-Id'));
            $this->assertSame('{"id":2,"name":"Bob"}', $second->body);
            $this->assertSame(['bob'], $second->headers->values('X-Request-Id'));
            $this->assertSame('Ada', $application->handle(new Request('GET', '/users/1'))->body);
            $this->assertSame('Bob', $application->handle(new Request('GET', '/users/2'))->body);
        } finally {
            $container->close();
        }
    }

    public function testRejectedInputDoesNotCreateUsersAndSubsequentRequestsStillWork(): void
    {
        $container = new ApplicationContainer();
        $application = new Application($container);
        try {
            $this->assertSame(400, $application->handle($this->request('{', 'bad'))->status);
            $invalid = $application->handle($this->request('{"name":42}', 'invalid'));
            $this->assertSame(422, $invalid->status);
            $this->assertSame(['invalid'], $invalid->headers->values('X-Middleware-Id'));
            $success = $application->handle($this->request('{"name":"Ada"}', 'next'));
            $this->assertSame('{"id":1,"name":"Ada"}', $success->body);
            $this->assertSame(['next'], $success->headers->values('X-Request-Id'));
            $wrongMethod = $application->handle(new Request('GET', '/users'));
            $this->assertSame(405, $wrongMethod->status);
            $this->assertSame(['POST'], $wrongMethod->headers->values('Allow'));
        } finally {
            $container->close();
        }
    }

    public function testJsonFailuresStillReleaseTheRequestSession(): void
    {
        $container = new ApplicationContainer();
        $scope = new RequestScope($this->request('{}', 'invalid'));
        try {
            $context = $container->request($scope);
            $session = $context->session();
            $response = $container->create($scope)->handle($scope->request);
            $this->assertSame(422, $response->status);
            $this->assertSame(0, $session->closes);
            $scope->close();
            $this->assertSame(1, $session->closes);
            $repository = self::repository($container->repository());
            $this->assertSame(0, $repository->closes);
        } finally {
            $scope->close();
            $container->close();
        }
    }

    private function request(string $body, string $identity): Request
    {
        return new Request('POST', '/users', new Headers(['Content-Type' => ['application/json'], 'X-Request-Id' => [$identity]]), $body);
    }

    private static function repository(mixed $value): MemoryUserRepository
    {
        if (!$value instanceof MemoryUserRepository) {
            throw new LogicException('Expected the concrete repository.');
        }

        return $value;
    }
}
