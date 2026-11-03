<?php

/**
 * Verifies retained application state with fresh request data, failure recovery and one-
 * time shutdown cleanup.
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
use Phex\Runtime\Http\PersistentWebApplication;
use Phex\Tests\Compatibility\Fixtures\Errors;
use Phex\Tests\Compatibility\Fixtures\HttpOutput;
use Phex\Tests\Compatibility\Fixtures\Logging\SequentialRequestIds;
use Phex\Tests\Compatibility\Fixtures\RequestInput;
use PHPUnit\Framework\TestCase;

final class PersistentWebApplicationTest extends TestCase
{
    public function testApplicationStateSurvivesWhileRequestIdentityIsFresh(): void
    {
        $container = new ApplicationContainer();
        $application = new PersistentWebApplication($container, new Errors(), new SequentialRequestIds());
        try {
            foreach ([1, 2] as $id) {
                $output = new HttpOutput();
                $input = new Request('POST', '/users', new Headers(['Content-Type' => ['application/json'], 'X-Request-Id' => ['client']]), '{"name":"Ada"}');
                $application->serve(new RequestInput($input), $output);
                $this->assertSame(201, $output->status);
                $this->assertSame('{"id":'.$id.',"name":"Ada"}', $output->body);
                $this->assertSame(['request-'.$id], $output->headers()->values('X-Request-Id'));
                $this->assertSame(['request-'.$id], $output->headers()->values('X-Middleware-Id'));
            }
            $this->assertSame('Ada', $container->repository()->name(2));
        } finally {
            $application->close();
        }
    }

    public function testRejectedInputAndUnexpectedFailuresDoNotCloseTheApplication(): void
    {
        $container = new ApplicationContainer();
        $errors = new Errors();
        $application = new PersistentWebApplication($container, $errors, new SequentialRequestIds());
        try {
            $invalid = new HttpOutput();
            $application->serve(new RequestInput(new Request('HEAD', '/'), true), $invalid, 'HEAD');
            $this->assertSame(400, $invalid->status);
            $this->assertSame('', $invalid->body);
            $failure = new HttpOutput();
            $application->serve(new RequestInput(new Request('GET', '/'), false, true), $failure);
            $this->assertSame(500, $failure->status);
            $this->assertSame('Internal Server Error', $failure->body);
            $this->assertSame('private failure', $errors->message);
            $this->assertSame('request-2', $errors->context['request_id']);
            $success = new HttpOutput();
            $application->serve(new RequestInput(new Request('GET', '/users/7')), $success);
            $this->assertSame(200, $success->status);
            $this->assertSame('user-7', $success->body);
            $this->assertSame(['request-3'], $success->headers()->values('X-Middleware-Id'));
            $this->assertSame('user-7', $container->repository()->name(7));
        } finally {
            $application->close();
        }
    }

    public function testApplicationResourcesCloseOnlyOnceAtShutdown(): void
    {
        $container = new ApplicationContainer();
        $repository = self::repository($container->repository());
        $application = new PersistentWebApplication($container, new Errors());
        $application->serve(new RequestInput(new Request('GET', '/users/7')), new HttpOutput());
        $this->assertSame(0, $repository->closes);
        $this->assertSame(0, $repository->connection->closes);
        $application->close();
        $application->close();
        $this->assertSame(1, $repository->closes);
        $this->assertSame(1, $repository->connection->closes);
    }

    public function testShutdownPreventsFurtherRequestDispatch(): void
    {
        $application = new PersistentWebApplication(new ApplicationContainer(), new Errors());
        $application->close();
        $this->expectException(LogicException::class);
        $application->serve(new RequestInput(new Request('GET', '/')), new HttpOutput());
    }

    private static function repository(mixed $value): MemoryUserRepository
    {
        if (!$value instanceof MemoryUserRepository) {
            throw new LogicException('The fixture needs its memory repository.');
        }

        return $value;
    }
}
