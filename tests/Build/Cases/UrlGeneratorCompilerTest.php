<?php

/**
 * Verifies fixed named-route generation, deterministic output and reserved URL-generator
 * dependencies.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Cases;

use Phex\Build\Analysis\Analyzer;
use Phex\Build\Analysis\InvalidContainer;
use Phex\Build\Compiler\ContainerCompiler;
use Phex\Build\Compiler\BuildConfig;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\EndpointRoute;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Contracts\Routing\UrlGenerator;
use Phex\Http\Message\Request;
use Phex\Kernel\Scope\RequestScope;
use Phex\Runtime\Development\DevelopmentContainer;
use Phex\Routing\Url\TableUrlGenerator;
use Phex\Tests\Compatibility\Fixtures\RoutingEchoEndpoint;
use Phex\Tests\Compatibility\Fixtures\UrlCases;
use Phex\Tests\Compatibility\Fixtures\UrlConsumer;
use Phex\Tests\Generated\NamedRoutingContainer;
use PHPUnit\Framework\TestCase;

final class UrlGeneratorCompilerTest extends TestCase
{
    public function testTheGraphAndGeneratedMatcherRetainTheName(): void
    {
        $graph = (new Analyzer())->analyze($this->definition([
            new EndpointRoute('GET', '/users/{id}', RoutingEchoEndpoint::class, name: 'users.show'),
        ]));
        $this->assertSame('users.show', $graph->routes[0]->name);
        $source = (new ContainerCompiler())->compile($graph->definition, 'Test\\Generated');
        $this->assertStringContainsString('name: "users.show"', $source);
    }

    public function testTheGeneratorUsesPreEncodedLiteralsAndFixedParameterLookups(): void
    {
        $source = (new ContainerCompiler())->compile($this->definition([
            new EndpointRoute('GET', '/caf'."\xc3\xa9".'/{id}', RoutingEchoEndpoint::class, name: 'users.show'),
        ]), 'Test\\Generated');
        $this->assertStringContainsString('new \\Test\\GeneratedUrlGenerator()', $source);
        $this->assertStringContainsString('if ($name === "users.show")', $source);
        $this->assertStringContainsString('return "/caf%C3%A9/".\\Phex\\Routing\\Url\\UrlParameters::segment($parameters, "id");', $source);
        $this->assertStringNotContainsString('TableUrlGenerator', $source);
        $this->assertStringNotContainsString('PathEncoder::encode', $source);
        $this->assertStringNotContainsString('->pattern->segments()', $source);
        $this->assertStringNotContainsString('eval(', $source);
    }

    public function testNamedGenerationIsDeterministicAcrossDefinitionOrder(): void
    {
        $routes = [];
        foreach (UrlCases::definitions() as [$name, $method, $path]) {
            $routes[] = new EndpointRoute($method, $path, RoutingEchoEndpoint::class, name: $name);
        }
        $compiler = new ContainerCompiler();
        $this->assertSame(
            $compiler->compile($this->definition($routes), 'Test\\Generated'),
            $compiler->compile($this->definition(array_reverse($routes)), 'Test\\Generated'),
        );
    }

    public function testDuplicateNamesAreRejectedAcrossMethodsBeforeEmission(): void
    {
        $this->expectException(InvalidContainer::class);
        $this->expectExceptionMessage('Duplicate route name');
        (new ContainerCompiler())->compile($this->definition([
            new EndpointRoute('GET', '/one', RoutingEchoEndpoint::class, name: 'same'),
            new EndpointRoute('POST', '/two', RoutingEchoEndpoint::class, name: 'same'),
        ]), 'Test\\Generated');
    }

    public function testInvalidNamesAreRejectedByBothBuildAndDevelopmentAnalysis(): void
    {
        $this->expectException(InvalidContainer::class);
        new DevelopmentContainer($this->definition([
            new EndpointRoute('GET', '/', RoutingEchoEndpoint::class, name: 'invalid name'),
        ]));
    }

    public function testTheGeneratorBindingIsReserved(): void
    {
        $this->expectException(InvalidContainer::class);
        (new Analyzer())->analyze(new Container([
            Service::constructor(UrlGenerator::class, Lifetime::Application, TableUrlGenerator::class),
        ]));
    }

    public function testTheGeneratedAccessorNameIsReservedForEntries(): void
    {
        $this->expectException(InvalidContainer::class);
        (new Analyzer())->analyze(new Container([
            Service::constructor(UrlConsumer::class, Lifetime::Application),
            Service::constructor(RoutingEchoEndpoint::class, Lifetime::Request),
        ], ['urls' => UrlConsumer::class], [new EndpointRoute('GET', '/', RoutingEchoEndpoint::class)]));
    }

    public function testBuiltinUrlGenerationNeedsAnExplicitRouteTable(): void
    {
        $this->expectException(InvalidContainer::class);
        $this->expectExceptionMessage('requires compiled routes');
        (new Analyzer())->analyze(new Container([Service::constructor(UrlConsumer::class, Lifetime::Application)]));
    }

    public function testDevelopmentAndGeneratedContainersShareUrlSemanticsAndApplicationLifetime(): void
    {
        $config = require __DIR__.'/../named-routing.php';
        $this->assertInstanceOf(BuildConfig::class, $config);
        $development = new DevelopmentContainer($config->container);
        $compiled = new NamedRoutingContainer();
        $scope = new RequestScope(new Request('GET', '/'));
        try {
            $this->assertSame($development->urls(), $development->get(UrlGenerator::class));
            $this->assertSame($development->urls(), $development->get(UrlConsumer::class)->urls);
            $this->assertSame($development->urls(), $development->request($scope)->get(UrlGenerator::class));
            foreach (['a/b', 'a+b c', '%2F', "caf\xc3\xa9", '0'] as $name) {
                $this->assertSame(
                    $compiled->urls()->generate('files.show', ['name' => $name]),
                    $development->urls()->generate('files.show', ['name' => $name]),
                );
            }
        } finally {
            $scope->close();
            $development->close();
            $compiled->close();
        }
    }

    /** @param list<EndpointRoute> $routes */
    private function definition(array $routes): Container
    {
        return new Container([
            Service::constructor(RoutingEchoEndpoint::class, Lifetime::Request),
        ], routes: $routes);
    }
}
