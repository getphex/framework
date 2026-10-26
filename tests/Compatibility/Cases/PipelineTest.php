<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use Fiber;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Middleware\Pipeline;
use Phex\Tests\Compatibility\Fixtures\EchoHandler;
use Phex\Tests\Compatibility\Fixtures\FailOnceHandler;
use Phex\Tests\Compatibility\Fixtures\RejectMiddleware;
use Phex\Tests\Compatibility\Fixtures\RetryMiddleware;
use Phex\Tests\Compatibility\Fixtures\SuspendMiddleware;
use Phex\Tests\Compatibility\Fixtures\TraceMiddleware;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class PipelineTest extends TestCase
{
    public function testMiddlewareRunsOutsideInAndResponsesUnwindInsideOut(): void
    {
        $handler = new EchoHandler();
        $inner = Pipeline::wrap(new TraceMiddleware('inner'), $handler);
        $pipeline = Pipeline::wrap(new TraceMiddleware('outer'), $inner);
        $request = new Request('GET', '/', new Headers(['X-Request' => ['alice']]), 'hello');
        $response = $pipeline->handle($request);

        $this->assertSame(200, $response->status);
        $this->assertSame('hello', $response->body);
        $this->assertSame(['outer', 'inner'], $response->headers->values('X-Trace'));
        $this->assertSame(['inner', 'outer'], $response->headers->values('X-Unwind'));
        $this->assertSame(['alice'], $response->headers->values('X-Request'));
        $this->assertFalse($request->headers->has('X-Trace'));
        $this->assertSame(1, $handler->calls);
    }

    public function testMiddlewareCanShortCircuitTheChain(): void
    {
        $handler = new EchoHandler();
        $pipeline = Pipeline::wrap(new RejectMiddleware(), $handler);
        $response = $pipeline->handle(new Request('GET', '/'));

        $this->assertSame(403, $response->status);
        $this->assertSame('forbidden', $response->body);
        $this->assertSame(0, $handler->calls);
    }

    public function testSequentialRequestsDoNotShareRequestData(): void
    {
        $handler = new EchoHandler();
        $pipeline = Pipeline::wrap(new TraceMiddleware('shared'), $handler);
        $first = $pipeline->handle(new Request('GET', '/', new Headers(['X-Request' => ['alice']]), 'first'));
        $second = $pipeline->handle(new Request('GET', '/', new Headers(['X-Request' => ['bob']]), 'second'));

        $this->assertSame('first', $first->body);
        $this->assertSame('second', $second->body);
        $this->assertSame(['alice'], $first->headers->values('X-Request'));
        $this->assertSame(['bob'], $second->headers->values('X-Request'));
        $this->assertSame(['shared'], $first->headers->values('X-Trace'));
        $this->assertSame(['shared'], $second->headers->values('X-Trace'));
        $this->assertSame(2, $handler->calls);
    }

    public function testCallingNextTwiceRunsTheEntireRemainingChainEachTime(): void
    {
        $handler = new EchoHandler();
        $inner = Pipeline::wrap(new TraceMiddleware('inner'), $handler);
        $pipeline = Pipeline::wrap(new RetryMiddleware(), $inner);
        $response = $pipeline->handle(new Request('GET', '/', new Headers(['X-Request' => ['alice']]), 'hello'));

        $this->assertSame('hello retried', $response->body);
        $this->assertSame(['inner'], $response->headers->values('X-Trace'));
        $this->assertSame(['inner'], $response->headers->values('X-Unwind'));
        $this->assertSame(2, $handler->calls);
    }

    public function testExceptionsPropagateAndThePipelineCanBeReused(): void
    {
        $pipeline = Pipeline::wrap(new TraceMiddleware('shared'), new FailOnceHandler());
        $caught = false;

        try {
            $pipeline->handle(new Request('GET', '/', body: 'first'));
        } catch (RuntimeException $error) {
            $caught = true;
            $this->assertSame('handler failed', $error->getMessage());
        }

        $this->assertTrue($caught);
        $response = $pipeline->handle(new Request('GET', '/', body: 'second'));
        $this->assertSame('second', $response->body);
        $this->assertSame(['shared'], $response->headers->values('X-Unwind'));
    }

    public function testInterleavedFibersKeepTheirOwnRequests(): void
    {
        $handler = new EchoHandler();
        $inner = Pipeline::wrap(new SuspendMiddleware(), $handler);
        $pipeline = Pipeline::wrap(new TraceMiddleware('outer'), $inner);
        $firstRequest = new Request('GET', '/', new Headers(['X-Request' => ['alice']]), 'first');
        $secondRequest = new Request('GET', '/', new Headers(['X-Request' => ['bob']]), 'second');
        $first = new Fiber(fn () => $pipeline->handle($firstRequest));
        $second = new Fiber(fn () => $pipeline->handle($secondRequest));

        $this->assertSame('first', $first->start());
        $this->assertSame('second', $second->start());
        $second->resume();
        $first->resume();
        $firstResponse = $first->getReturn();
        $secondResponse = $second->getReturn();

        $this->assertSame('first', $firstResponse->body);
        $this->assertSame('second', $secondResponse->body);
        $this->assertSame(['alice'], $firstResponse->headers->values('X-Request'));
        $this->assertSame(['bob'], $secondResponse->headers->values('X-Request'));
        $this->assertSame(['outer'], $firstResponse->headers->values('X-Trace'));
        $this->assertSame(['outer'], $secondResponse->headers->values('X-Trace'));
        $this->assertSame(2, $handler->calls);
    }
}
