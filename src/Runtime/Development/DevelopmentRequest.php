<?php

declare(strict_types=1);

namespace Phex\Runtime\Development;

use LogicException;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Lifetime\ApplicationScope;
use Phex\Container\Routing\EndpointReference;
use Phex\Contracts\Http\Handler;
use Phex\Contracts\Kernel\ScopeResource;
use Phex\Contracts\Routing\Endpoint;
use Phex\Contracts\Routing\EndpointResolver;
use Phex\Contracts\Routing\Matcher;
use Phex\Contracts\Routing\UrlGenerator;
use Phex\Http\Message\Request;
use Phex\Kernel\Scope\RequestScope;
use Phex\Routing\Router;

final class DevelopmentRequest implements ScopeResource, EndpointResolver
{
    private bool $closed = false;
    private readonly ServiceCache $services;

    /** @internal Use DevelopmentContainer::request to bind the context to its scope. */
    public function __construct(private readonly DevelopmentContainer $application, private readonly RequestScope $scope)
    {
        $this->services = new ServiceCache($scope);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    public function get(string $type): object
    {
        $value = $this->service($this->application->type($type));
        if (!$value instanceof $type) {
            throw new LogicException('Incompatible service: '.$type);
        }

        return $value;
    }

    public function handler(): Handler
    {
        $this->assertOpen();
        $type = $this->application->graph->definition->handler;
        if ($type !== null) {
            return $this->get($type);
        }

        return new Router($this->application->matcher(), $this);
    }

    public function resolve(Endpoint $endpoint): Endpoint
    {
        $this->assertOpen();
        if (!$endpoint instanceof EndpointReference) {
            return $endpoint;
        }
        foreach ($this->application->graph->routes as $route) {
            if ($endpoint->type === $route->endpoint) {
                return $this->get($route->endpoint);
            }
        }

        throw new LogicException('Unknown compiled endpoint.');
    }

    public function close(): void
    {
        $this->closed = true;
    }

    private function assertOpen(): void
    {
        $this->application->assertOpen();
        if ($this->closed || $this->scope->isClosed()) {
            throw new LogicException('The request container is closed.');
        }
    }

    private function service(string $type): object
    {
        $this->assertOpen();
        if ($type === RequestScope::class) {
            return $this->scope;
        }
        if ($type === Request::class) {
            return $this->scope->request;
        }
        if ($type === EndpointResolver::class) {
            return $this;
        }
        if ($type === Matcher::class) {
            return $this->application->matcher();
        }
        if ($type === UrlGenerator::class) {
            return $this->application->urls();
        }
        if ($type === ApplicationScope::class) {
            return $this->application->get(ApplicationScope::class);
        }
        $node = $this->application->graph->nodes[$type] ?? throw new LogicException('Unknown request service: '.$type);
        if ($node->lifetime === Lifetime::Application) {
            return $this->application->get($node->type);
        }

        return $this->services->resolve($node, $this->service(...));
    }
}
