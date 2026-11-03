<?php

/**
 * Verifies matching precedence, method errors, decoded path captures and endpoint dispatch
 * through middleware.
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
use Phex\Examples\HelloWorld\JsonMiddleware;
use Phex\Examples\JsonApi\JsonApi;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Http\Middleware\Pipeline;
use Phex\Routing\Definition\Route;
use Phex\Routing\Matcher\MatchStatus;
use Phex\Routing\Matcher\TableMatcher;
use Phex\Routing\Router;
use Phex\Tests\Compatibility\Fixtures\FailingEndpoint;
use Phex\Tests\Compatibility\Fixtures\FixedMatcher;
use Phex\Tests\Compatibility\Fixtures\PausingEndpoint;
use Phex\Tests\Compatibility\Fixtures\TextEndpoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RouterTest extends TestCase
{
    public function testStaticAndParameterizedRoutesWorkInsideTheExistingPipeline(): void
    {
        $application = JsonApi::application();
        $health = $application->handle(new Request('GET', '/health'));
        $user = $application->handle(new Request('GET', '/users/42?ignored=1'));

        $this->assertSame(200, $health->status);
        $this->assertSame('{"status":"ok"}', $health->body);
        $this->assertSame('{"id":42}', $user->body);
        $this->assertSame(['application/json'], $user->headers->values('Content-Type'));
    }

    public function testNotFoundAndMethodNotAllowedHaveDifferentResults(): void
    {
        $application = JsonApi::application();
        $missing = $application->handle(new Request('GET', '/missing'));
        $wrongMethod = $application->handle(new Request('POST', '/users/42'));

        $this->assertSame(404, $missing->status);
        $this->assertFalse($missing->headers->has('Allow'));
        $this->assertSame(405, $wrongMethod->status);
        $this->assertSame(['GET'], $wrongMethod->headers->values('Allow'));
    }

    public function testAllowedMethodsAreUniqueAndSortedAcrossMatchingPaths(): void
    {
        $router = new Router(new TableMatcher([
            new Route('POST', '/users/me', new TextEndpoint('post')),
            new Route('GET', '/users/{id}', new TextEndpoint('get')),
            new Route('GET', '/users/me', new TextEndpoint('get me')),
            new Route('DELETE', '/users/{name}', new TextEndpoint('delete')),
        ]));
        $response = $router->handle(new Request('PATCH', '/users/me'));

        $this->assertSame(405, $response->status);
        $this->assertSame(['DELETE, GET, POST'], $response->headers->values('Allow'));
    }

    public function testStaticSegmentsWinRegardlessOfDefinitionOrder(): void
    {
        $router = new Router(new TableMatcher([
            new Route('GET', '/users/{id}', new TextEndpoint('parameter')),
            new Route('GET', '/{section}/me', new TextEndpoint('generic')),
            new Route('GET', '/users/me', new TextEndpoint('static')),
        ]));

        $this->assertSame('static', $router->handle(new Request('GET', '/users/me'))->body);
        $this->assertSame('parameter', $router->handle(new Request('GET', '/users/42'))->body);
    }

    public function testEarlierLiteralSegmentsDetermineOverlappingPatternPrecedence(): void
    {
        $router = new Router(new TableMatcher([
            new Route('GET', '/{section}/new', new TextEndpoint('later literal')),
            new Route('GET', '/users/{id}', new TextEndpoint('earlier literal')),
        ]));

        $this->assertSame('earlier literal', $router->handle(new Request('GET', '/users/new'))->body);
    }

    public function testAnIncompatibleStaticMethodDoesNotHideACompatibleParameterizedRoute(): void
    {
        $router = new Router(new TableMatcher([
            new Route('POST', '/users/me', new TextEndpoint('static')),
            new Route('GET', '/users/{id}', new TextEndpoint('parameter')),
        ]));

        $this->assertSame('parameter', $router->handle(new Request('GET', '/users/me'))->body);
    }

    public function testMultipleParametersAreSeparateFromTheOriginalRequest(): void
    {
        $request = new Request('GET', '/teams/7/users/42?name=alice', body: 'payload');
        $matcher = new TableMatcher([
            new Route('GET', '/teams/{team}/users/{user}', new TextEndpoint('unused')),
        ]);
        $result = $matcher->match($request);

        $this->assertSame(MatchStatus::Found, $result->status);
        $this->assertSame(['team' => '7', 'user' => '42'], $result->parameters->all());
        $this->assertSame('/teams/7/users/42?name=alice', $request->uri);
        $this->assertSame('payload', $request->body);
    }

    public function testEncodedSegmentsAreDecodedOnceAfterSplittingThePath(): void
    {
        $matcher = new TableMatcher([
            new Route('GET', '/files/{name}', new TextEndpoint('unused')),
        ]);

        $this->assertSame('a/b', $matcher->match(new Request('GET', '/files/a%2Fb'))->parameters->get('name'));
        $this->assertSame('%2F', $matcher->match(new Request('GET', '/files/%252F'))->parameters->get('name'));
        $this->assertSame('a+b c?', $matcher->match(new Request('GET', '/files/a+b%20c%3F'))->parameters->get('name'));
        $this->assertSame(MatchStatus::NotFound, $matcher->match(new Request('GET', '/files/a/b'))->status);
    }

    public function testDecodedStaticLiteralsAndUtf8AreMatched(): void
    {
        $router = new Router(new TableMatcher([
            new Route('GET', '/hello world', new TextEndpoint('space')),
            new Route('GET', '/caf'."\xc3\xa9", new TextEndpoint('unicode')),
        ]));

        $this->assertSame('space', $router->handle(new Request('GET', '/hello%20world'))->body);
        $this->assertSame('unicode', $router->handle(new Request('GET', '/caf%C3%A9'))->body);
    }

    public function testLiteralAndEncodedPlusSignsArePreserved(): void
    {
        $matcher = new TableMatcher([new Route('GET', '/files/{name}', new TextEndpoint('unused'))]);

        $this->assertSame('a+b', $matcher->match(new Request('GET', '/files/a+b'))->parameters->get('name'));
        $this->assertSame('a+b', $matcher->match(new Request('GET', '/files/a%2Bb'))->parameters->get('name'));
        $this->assertSame('%2B', $matcher->match(new Request('GET', '/files/%252B'))->parameters->get('name'));
        $this->assertSame('a b', $matcher->match(new Request('GET', '/files/a%20b'))->parameters->get('name'));
    }

    public function testTheRouterCanUseAnAlternativePrecompiledMatcher(): void
    {
        $route = new Route('GET', '/compiled/{id}', new \Phex\Examples\JsonApi\UserEndpoint());
        $router = new Router(new FixedMatcher($route));

        $this->assertSame('{"id":42}', $router->handle(new Request('GET', '/compiled/42'))->body);
    }

    public function testQueryStringsDoNotParticipateInPathMatchingOrDecoding(): void
    {
        $router = new Router(new TableMatcher([new Route('GET', '/', new TextEndpoint('root'))]));

        $this->assertSame('root', $router->handle(new Request('GET', '/?path=%GG'))->body);
    }

    public function testEmptySegmentsTrailingSlashesAndPathCaseAreSignificant(): void
    {
        $router = new Router(new TableMatcher([
            new Route('GET', '/users/{id}', new TextEndpoint('user')),
            new Route('GET', '/users/', new TextEndpoint('trailing')),
            new Route('GET', '/a//b', new TextEndpoint('double')),
        ]));

        $this->assertSame('trailing', $router->handle(new Request('GET', '/users/'))->body);
        $this->assertSame(404, $router->handle(new Request('GET', '/users/42/'))->status);
        $this->assertSame(404, $router->handle(new Request('GET', '/Users/42'))->status);
        $this->assertSame('double', $router->handle(new Request('GET', '/a//b'))->body);
        $this->assertSame(404, $router->handle(new Request('GET', '/a/b'))->status);
    }

    public function testMethodsAreExactAndHeadRequiresAnExplicitRoute(): void
    {
        $router = new Router(new TableMatcher([new Route('GET', '/health', new TextEndpoint('ok'))]));

        $this->assertSame(405, $router->handle(new Request('get', '/health'))->status);
        $this->assertSame(405, $router->handle(new Request('HEAD', '/health'))->status);
    }

    /** @return list<array{string}> */
    public static function invalidPaths(): array
    {
        return [
            ['/bad%'], ['/bad%2'], ['/bad%GG'], ['/bad%0G'], ['/bad%G0'],
            ['/bad%00'], ['/bad%09'], ['/bad%1f'], ['/bad%7F'],
            ['https://example.test/health'], ['*'], ['example.test:443'],
        ];
    }

    #[DataProvider('invalidPaths')]
    public function testMalformedPathsProduceBadRequest(string $uri): void
    {
        $router = new Router(new TableMatcher([]));

        $this->assertSame(400, $router->handle(new Request('GET', $uri))->status);
    }

    public function testAnEmptyRoutingTableReturnsNotFound(): void
    {
        $router = new Router(new TableMatcher([]));

        $this->assertSame(404, $router->handle(new Request('GET', '/'))->status);
    }

    public function testInvalidIntegerConversionProducesBadRequest(): void
    {
        $application = JsonApi::application();

        $this->assertSame(400, $application->handle(new Request('GET', '/users/alice'))->status);
        $this->assertSame(400, $application->handle(new Request('GET', '/users/'.PHP_INT_MAX.'0'))->status);
        $this->assertSame('{"id":42}', $application->handle(new Request('GET', '/users/42'))->body);
    }

    public function testUnexpectedEndpointErrorsPropagate(): void
    {
        $router = new Router(new TableMatcher([new Route('GET', '/', new FailingEndpoint())]));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('endpoint failed');
        $router->handle(new Request('GET', '/'));
    }

    public function testUnmatchedResultsCannotSupplyARoute(): void
    {
        $matcher = new TableMatcher([]);
        $result = $matcher->match(new Request('GET', '/'));
        $this->expectException(LogicException::class);
        $result->route();
    }

    public function testRoutingTablesDoNotRetainReferencesToTheInputList(): void
    {
        $routes = [new Route('GET', '/', new TextEndpoint('original'))];
        $router = new Router(new TableMatcher($routes));
        $routes[0] = new Route('GET', '/', new TextEndpoint('changed'));

        $this->assertSame('original', $router->handle(new Request('GET', '/'))->body);
    }

    public function testReusedRoutersDoNotShareParameterValues(): void
    {
        $application = JsonApi::application();
        $first = $application->handle(new Request('GET', '/users/7'));
        $second = $application->handle(new Request('GET', '/users/42'));

        $this->assertSame('{"id":7}', $first->body);
        $this->assertSame('{"id":42}', $second->body);
    }

    public function testInterleavedRequestsKeepTheirOwnRoutingResults(): void
    {
        $router = new Router(new TableMatcher([
            new Route('GET', '/users/{id}', new PausingEndpoint()),
        ]));
        $application = Pipeline::wrap(new JsonMiddleware(), $router);
        $first = new Fiber(fn () => $application->handle(new Request('GET', '/users/7', body: 'alice')));
        $second = new Fiber(fn () => $application->handle(new Request('GET', '/users/42', body: 'bob')));

        $this->assertSame('7', $first->start());
        $this->assertSame('42', $second->start());
        $second->resume();
        $first->resume();
        $firstResponse = $first->getReturn();
        $secondResponse = $second->getReturn();

        $this->assertTrue($firstResponse instanceof Response);
        $this->assertTrue($secondResponse instanceof Response);
        $this->assertSame('7:alice', $firstResponse->body);
        $this->assertSame('42:bob', $secondResponse->body);
    }
}
