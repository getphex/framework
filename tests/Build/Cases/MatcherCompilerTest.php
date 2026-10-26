<?php

declare(strict_types=1);

namespace Phex\Tests\Build\Cases;

use Phex\Build\Analysis\Analyzer;
use Phex\Build\Analysis\InvalidContainer;
use Phex\Build\Compiler\ContainerCompiler;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\EndpointRoute;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Tests\Compatibility\Fixtures\RoutingCases;
use Phex\Tests\Compatibility\Fixtures\RoutingEchoEndpoint;
use PHPUnit\Framework\TestCase;

final class MatcherCompilerTest extends TestCase
{
    public function testTheGraphCarriesValidatedSegmentsWithParameterNames(): void
    {
        $graph = (new Analyzer())->analyze($this->definition([
            new EndpointRoute('GET', '/teams/{team}/users/{id}', RoutingEchoEndpoint::class),
        ]));
        $route = $graph->routes[0];
        $this->assertSame(['teams', 'team', 'users', 'id'], array_map(static fn ($segment): string => $segment->value, $route->segments));
        $this->assertSame([false, true, false, true], array_map(static fn ($segment): bool => $segment->parameter, $route->segments));
    }

    public function testGeneratedRoutingIsDeterministicAcrossDefinitionOrder(): void
    {
        $routes = [];
        foreach (RoutingCases::definitions() as [$method, $path]) {
            $routes[] = new EndpointRoute($method, $path, RoutingEchoEndpoint::class);
        }
        $compiler = new ContainerCompiler();
        $first = $compiler->compile($this->definition($routes), 'Test\\Generated');
        $second = $compiler->compile($this->definition(array_reverse($routes)), 'Test\\Generated');
        $this->assertSame($first, $second);
    }

    public function testTheArtifactUsesCompiledMetadataAndFixedMatcherCode(): void
    {
        $source = (new ContainerCompiler())->compile($this->definition([
            new EndpointRoute('GET', '/users/{id}', RoutingEchoEndpoint::class),
            new EndpointRoute('POST', '/users/me', RoutingEchoEndpoint::class),
        ]), 'Test\\Generated');
        $this->assertStringContainsString('new \\Test\\GeneratedMatcher()', $source);
        $this->assertStringContainsString('compiledSegments: [new \\Phex\\Routing\\Definition\\Segment(', $source);
        $this->assertStringContainsString('PathSegments::fromUri($request->uri)', $source);
        $this->assertStringContainsString('if ($part1 === "me")', $source);
        $this->assertStringContainsString('new \\Phex\\Routing\\Parameter\\Parameters(["id" => $part1])', $source);
        $this->assertStringNotContainsString('TableMatcher', $source);
        $this->assertStringNotContainsString('new \\Phex\\Routing\\Definition\\PathPattern(', $source);
        $this->assertStringNotContainsString('->pattern->match(', $source);
        $this->assertStringNotContainsString('->precedes(', $source);
        $this->assertStringNotContainsString('sort(', $source);
        $this->assertStringNotContainsString('eval(', $source);
    }

    public function testCompilationRejectsAmbiguousShapesRegardlessOfNames(): void
    {
        $this->expectException(InvalidContainer::class);
        $this->expectExceptionMessage('Ambiguous route');
        (new ContainerCompiler())->compile($this->definition([
            new EndpointRoute('GET', '/users/{first}', RoutingEchoEndpoint::class),
            new EndpointRoute('GET', '/users/{second}', RoutingEchoEndpoint::class),
        ]), 'Test\\Generated');
    }

    public function testOnlyTheGeneratedArtifactReceivesTrustedSegments(): void
    {
        $this->expectException(InvalidContainer::class);
        (new ContainerCompiler())->compile($this->definition([
            new EndpointRoute('GET', '/users/{id}/{id}', RoutingEchoEndpoint::class),
        ]), 'Test\\Generated');
    }

    /** @param list<EndpointRoute> $routes */
    private function definition(array $routes): Container
    {
        return new Container([Service::constructor(RoutingEchoEndpoint::class, Lifetime::Request)], routes: $routes);
    }
}
