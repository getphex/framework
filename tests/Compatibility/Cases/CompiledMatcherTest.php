<?php

/**
 * Compares compiled and table routing across shared method/path cases and independent
 * parameter captures.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use Fiber;
use LogicException;
use Phex\Http\Message\Request;
use Phex\Kernel\Application;
use Phex\Routing\Definition\PathPattern;
use Phex\Routing\Definition\Route;
use Phex\Routing\Definition\Segment;
use Phex\Routing\Matcher\MatchStatus;
use Phex\Routing\Matcher\PathSegments;
use Phex\Tests\Compatibility\Fixtures\RoutingCases;
use Phex\Tests\Compatibility\Fixtures\RoutingEchoEndpoint;
use Phex\Tests\Generated\EmptyRoutingContainer;
use Phex\Tests\Generated\EmptyRoutingContainerMatcher;
use Phex\Tests\Generated\RoutingContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CompiledMatcherTest extends TestCase
{
    /** @return list<array{string, string}> */
    public static function requests(): array
    {
        $cases = [];
        foreach (RoutingCases::targets() as $uri) {
            foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'HEAD', 'OPTIONS', 'get', '10', '2', 'MISSING'] as $method) {
                $cases[] = [$method, $uri];
            }
        }
        foreach (['/bad%', '/bad%2', '/bad%GG', '/bad%0G', '/bad%G0', '/bad%00', '/bad%09', '/bad%1f', '/bad%7F', 'https://example.test/users/7', '*', 'example.test:443'] as $uri) {
            $cases[] = ['GET', $uri];
        }

        return $cases;
    }

    #[DataProvider('requests')]
    public function testTheCompiledMatcherAgreesWithTheTableMatcher(string $method, string $uri): void
    {
        $container = new RoutingContainer();
        try {
            $request = new Request($method, $uri);
            $expected = RoutingCases::table()->match($request);
            $actual = $container->matcher()->match($request);
            $this->assertSame($expected->status, $actual->status);
            $this->assertSame($expected->parameters->all(), $actual->parameters->all());
            $this->assertSame($expected->allowedMethods, $actual->allowedMethods);
            if ($actual->status === MatchStatus::Found) {
                $this->assertSame($expected->route()->method, $actual->route()->method);
                $this->assertSame($expected->route()->path, $actual->route()->path);
            }
        } finally {
            $container->close();
        }
    }

    public function testMethodFallbackAndMethodSpecificCaptureNames(): void
    {
        $container = new RoutingContainer();
        $application = new Application($container);
        try {
            $this->assertSame('GET me', $application->handle(new Request('GET', '/method/me'))->body);
            $this->assertSame('POST ', $application->handle(new Request('POST', '/method/me'))->body);
            $this->assertSame(['name' => 'alice'], $container->matcher()->match(new Request('GET', '/people/alice'))->parameters->all());
            $this->assertSame(['identifier' => 'alice'], $container->matcher()->match(new Request('POST', '/people/alice'))->parameters->all());
            $this->assertSame(['10, 2'], $application->handle(new Request('GET', '/numeric'))->headers->values('Allow'));
        } finally {
            $container->close();
        }
    }

    public function testCompiledRouteMetadataRetainsPatternOperations(): void
    {
        $container = new RoutingContainer();
        try {
            $result = $container->matcher()->match(new Request('GET', '/users/7/posts/42'));
            $parameters = $result->route()->pattern->match(PathSegments::fromUri('/users/7/posts/42'));
            if ($parameters === null) {
                throw new LogicException('The precompiled pattern must still match.');
            }
            $this->assertSame(['id' => '7', 'post' => '42'], $parameters->all());
            $this->assertSame('/users/{id}/posts/{post}', $result->route()->pattern->path);
            $this->assertTrue($result->route()->pattern->sameShape(new PathPattern('/users/{who}/posts/{which}')));
            $this->assertTrue($result->route()->pattern->precedes(new PathPattern('/{section}/{id}/posts/{post}')));
        } finally {
            $container->close();
        }
    }

    public function testPrevalidatedSegmentsAreDefensivelyCopied(): void
    {
        $segments = [new Segment('files', false), new Segment('name', true)];
        $route = new Route('GET', '/files/{name}', new RoutingEchoEndpoint(), $segments);
        $segments[0] = new Segment('changed', false);
        $export = $route->pattern->segments();
        $export[1] = new Segment('changed', true);
        $parameters = $route->pattern->match(['files', 'one']);
        if ($parameters === null) {
            throw new LogicException('The original pattern must not change.');
        }
        $this->assertSame(['name' => 'one'], $parameters->all());
    }

    public function testEmptyCompiledMatchersStillValidateIncomingPaths(): void
    {
        // Loading the artifact declares both the container and its fixed matcher class.
        $container = new EmptyRoutingContainer();
        $matcher = new EmptyRoutingContainerMatcher();
        $this->assertSame(MatchStatus::NotFound, $matcher->match(new Request('GET', '/'))->status);
        $this->assertSame(MatchStatus::BadRequest, $matcher->match(new Request('GET', '/bad%GG'))->status);
        $container->close();
    }

    public function testInterleavedCallsDoNotShareCapturedValues(): void
    {
        $container = new RoutingContainer();
        $matcher = $container->matcher();
        $first = new Fiber(function () use ($matcher): string {
            $result = $matcher->match(new Request('GET', '/users/7'));
            Fiber::suspend();

            return $result->parameters->get('id');
        });
        $second = new Fiber(fn (): string => $matcher->match(new Request('GET', '/users/42'))->parameters->get('id'));
        try {
            $first->start();
            $second->start();
            $first->resume();
            $this->assertSame('7', $first->getReturn());
            $this->assertSame('42', $second->getReturn());
        } finally {
            $container->close();
        }
    }
}
