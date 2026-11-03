<?php

/**
 * Interprets validated service definitions for build-free PHP development with explicit
 * application ownership.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Development;

use LogicException;
use Phex\Build\Analysis\Analyzer;
use Phex\Build\Graph\Graph;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\DefinitionLoader;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Lifetime\ApplicationScope;
use Phex\Container\Routing\EndpointReference;
use Phex\Contracts\Http\Handler;
use Phex\Contracts\Container\ApplicationContainer;
use Phex\Contracts\Routing\Matcher;
use Phex\Contracts\Routing\UrlGenerator;
use Phex\Kernel\Scope\RequestScope;
use Phex\Routing\Definition\Route;
use Phex\Routing\Matcher\TableMatcher;
use Phex\Routing\Url\TableUrlGenerator;
use Throwable;

/** PHP development only. Load from a dedicated entry point outside the native graph. */
final class DevelopmentContainer implements ApplicationContainer
{
    /** @internal */
    public readonly Graph $graph;
    private readonly ApplicationScope $resources;
    private readonly ServiceCache $services;
    private readonly Matcher $routes;
    private readonly UrlGenerator $urls;

    /** @var array<string, string> */
    private array $types = [];

    public function __construct(Container $definition)
    {
        $this->graph = (new Analyzer())->analyze($definition);
        $this->resources = new ApplicationScope();
        $this->services = new ServiceCache($this->resources);
        foreach ([...array_keys($this->graph->nodes), ...array_keys(Analyzer::BUILTINS)] as $type) {
            $this->types[strtolower($type)] = $type;
        }
        try {
            $routes = [];
            foreach ($this->graph->routes as $route) {
                $routes[] = new Route($route->method, $route->path, new EndpointReference($route->endpoint), name: $route->name);
            }
            $this->routes = new TableMatcher($routes);
            $this->urls = new TableUrlGenerator($routes);
            foreach ($this->graph->nodes as $node) {
                if ($node->lifetime === Lifetime::Application) {
                    $this->service($node->type);
                }
            }
        } catch (Throwable $failure) {
            $this->resources->close($failure);
            throw $failure;
        }
    }

    public static function fromFile(string $path): DevelopmentContainer
    {
        return new DevelopmentContainer(DefinitionLoader::container($path));
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    public function get(string $type): object
    {
        $canonical = $this->type($type);
        if (($this->graph->nodes[$canonical]->lifetime ?? Analyzer::BUILTINS[$canonical]) !== Lifetime::Application) {
            throw new LogicException('Resolve non-application services through a request context.');
        }
        $value = $this->service($canonical);
        if (!$value instanceof $type) {
            throw new LogicException('Incompatible service: '.$type);
        }

        return $value;
    }

    public function request(RequestScope $scope): DevelopmentRequest
    {
        $this->assertOpen();
        $context = $scope->context($this);
        if ($context instanceof DevelopmentRequest) {
            return $context;
        }
        $created = new DevelopmentRequest($this, $scope);
        $scope->bindContext($this, $created);

        return $created;
    }

    public function create(RequestScope $scope): Handler
    {
        return $this->request($scope)->handler();
    }

    public function close(): void
    {
        $this->resources->close();
    }

    /** @internal */
    public function assertOpen(): void
    {
        if ($this->resources->isClosed()) {
            throw new LogicException('The application container is closed.');
        }
    }

    /** @internal Canonical names are indexed at bootstrap, without reflection on resolution. */
    public function type(string $type): string
    {
        $this->assertOpen();

        return $this->types[strtolower(ltrim($type, '\\'))] ?? throw new LogicException('Unknown service: '.$type);
    }

    /** @internal */
    public function matcher(): Matcher
    {
        $this->assertOpen();
        if ($this->graph->routes === []) {
            throw new LogicException('The container has no routes.');
        }

        return $this->routes;
    }

    private function service(string $type): object
    {
        $this->assertOpen();
        if ($type === ApplicationScope::class) {
            return $this->resources;
        }
        if ($type === Matcher::class) {
            return $this->matcher();
        }
        if ($type === UrlGenerator::class) {
            return $this->urls();
        }
        $node = $this->graph->nodes[$type] ?? throw new LogicException('Unknown application service: '.$type);
        if ($node->lifetime === Lifetime::Request) {
            throw new LogicException('Application services cannot resolve request state.');
        }

        return $this->services->resolve($node, $this->service(...));
    }

    public function urls(): UrlGenerator
    {
        $this->assertOpen();
        if ($this->graph->routes === []) {
            throw new LogicException('The container has no routes.');
        }

        return $this->urls;
    }
}
