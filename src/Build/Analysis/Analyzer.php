<?php

/**
 * Validates explicit service graphs, factory signatures, routes and dependency lifetimes
 * before code generation.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Analysis;

use Phex\Build\Emitter\Literal;
use Phex\Build\Graph\Argument;
use Phex\Build\Graph\Graph;
use Phex\Build\Graph\Node;
use Phex\Build\Graph\RouteNode;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Container\Lifetime\ApplicationScope;
use Phex\Container\Routing\EndpointReference;
use Phex\Contracts\Kernel\ScopeResource;
use Phex\Contracts\Http\Handler;
use Phex\Contracts\Routing\Endpoint;
use Phex\Contracts\Routing\EndpointResolver;
use Phex\Contracts\Routing\Matcher;
use Phex\Contracts\Routing\UrlGenerator;
use Phex\Http\Message\Request;
use Phex\Kernel\Scope\RequestScope;
use Phex\Routing\Definition\Route;
use Phex\Routing\Matcher\TableMatcher;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use ReflectionIntersectionType;
use InvalidArgumentException;

/** Build and PHP development bootstrap analysis. Emitted containers never load this class. */
final class Analyzer
{
    /** @var array<class-string, Lifetime> */
    public const array BUILTINS = [
        ApplicationScope::class => Lifetime::Application,
        Matcher::class => Lifetime::Application,
        UrlGenerator::class => Lifetime::Application,
        RequestScope::class => Lifetime::Request,
        Request::class => Lifetime::Request,
        EndpointResolver::class => Lifetime::Request,
    ];

    public function analyze(Container $definition): Graph
    {
        $services = [];
        foreach ($definition->services as $service) {
            $type = self::className($service->type);
            if (isset(self::BUILTINS[$type]) || isset($services[$type])) {
                throw new InvalidContainer('Duplicate or reserved binding: '.$type);
            }
            $services[$type] = $service;
        }
        ksort($services, SORT_STRING);
        $nodes = [];
        foreach ($services as $type => $service) {
            $nodes[$type] = $this->node($type, $service);
        }

        foreach ($nodes as $node) {
            foreach ($node->arguments as $argument) {
                $dependency = $argument->service;
                if ($dependency === null) {
                    continue;
                }
                if (($dependency === Matcher::class || $dependency === UrlGenerator::class) && $definition->routes === []) {
                    throw new InvalidContainer('The built-in matcher or URL generator requires compiled routes.');
                }
                if (!isset($nodes[$dependency]) && !isset(self::BUILTINS[$dependency])) {
                    throw new InvalidContainer('Missing binding: '.$node->type.' -> '.$dependency);
                }
            }
        }

        $complete = [];
        foreach (array_keys($nodes) as $type) {
            $this->visit($type, $nodes, [], $complete);
        }
        foreach ($nodes as $node) {
            if ($node->lifetime === Lifetime::Application) {
                $this->checkLifetime($node->type, $nodes, []);
            }
        }

        $names = [];
        foreach ($definition->entries as $name => $type) {
            $lower = strtolower($name);
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/D', $name) !== 1
                || preg_match('/^service[0-9]+$/D', $lower) === 1
                || in_array($lower, ['request', 'create', 'close', 'resolve', 'assertopen', 'ownership', 'matcher', 'urls', 'handler', 'registerresource'], true)
                || isset($names[$lower])) {
                throw new InvalidContainer('Invalid or reserved entry name: '.$name);
            }
            $names[$lower] = true;
            if (!isset($nodes[self::className($type)])) {
                throw new InvalidContainer('Missing entry binding: '.$type);
            }
        }

        $routes = [];
        $routeNodes = [];
        foreach ($definition->routes as $route) {
            $type = self::className($route->endpoint);
            if (!is_a($type, Endpoint::class, true) || !isset($nodes[$type])) {
                throw new InvalidContainer('Missing endpoint binding: '.$type);
            }
            try {
                $validated = new Route($route->method, $route->path, new EndpointReference($type), name: $route->name);
                $routes[] = $validated;
                $routeNodes[] = new RouteNode($route->method, $route->path, $type, $validated->pattern->segments(), $route->name);
            } catch (InvalidArgumentException $error) {
                throw new InvalidContainer('Invalid compiled route: '.$error->getMessage(), previous: $error);
            }
        }
        // Validate pattern syntax and ambiguities before emitting any file.
        try {
            new TableMatcher($routes);
        } catch (InvalidArgumentException $error) {
            throw new InvalidContainer('Invalid compiled routes: '.$error->getMessage(), previous: $error);
        }
        if ($definition->handler !== null && (!is_a($definition->handler, Handler::class, true) || !isset($nodes[self::className($definition->handler)]))) {
            throw new InvalidContainer('Missing handler binding: '.$definition->handler);
        }

        return new Graph($nodes, $definition, $routeNodes);
    }

    /**
     * @param class-string $type
     * @param Service<object> $service
     */
    private function node(string $type, Service $service): Node
    {
        $provider = new ReflectionClass(self::className($service->provider));
        $method = null;
        $resource = false;
        $possibleResource = false;
        if ($service->method === null) {
            if (!$provider->isInstantiable() || !is_a($provider->getName(), $type, true)) {
                throw new InvalidContainer('Invalid constructor provider for '.$type.': '.$provider->getName());
            }
            $method = $provider->getConstructor();
            $resource = $provider->implementsInterface(ScopeResource::class);
        } else {
            if (!$provider->hasMethod($service->method)) {
                throw new InvalidContainer('Missing static factory for '.$type);
            }
            $method = $provider->getMethod($service->method);
            $return = $method->getReturnType();
            if (!$method->isPublic() || !$method->isStatic() || $method->isAbstract() || $method->returnsReference()
                || !$return instanceof ReflectionNamedType || $return->allowsNull() || $return->isBuiltin()) {
                throw new InvalidContainer('Factory must be public static with a non-null object return type: '.$type);
            }
            $returnName = $this->namedType($return, $method, $provider->getName());
            if (!is_a($returnName, $type, true)) {
                throw new InvalidContainer('Incompatible factory return type for '.$type.': '.$returnName);
            }
            $returned = new ReflectionClass($returnName);
            $resource = $returned->implementsInterface(ScopeResource::class);
            $possibleResource = !$resource && !$returned->isFinal();
        }
        $arguments = [];
        foreach ($method?->getParameters() ?? [] as $parameter) {
            $arguments[] = $this->argument($parameter, $type, $provider->getName());
        }

        return new Node($type, $service->lifetime, $provider->getName(), $service->method === null ? null : $method->getName(), $arguments, $resource, $possibleResource);
    }

    /** @param class-string $provider */
    private function argument(ReflectionParameter $parameter, string $service, string $provider): Argument
    {
        if ($parameter->isVariadic() || $parameter->isPassedByReference()) {
            throw new InvalidContainer('Variadic or by-reference dependency in '.$service.'::$'.$parameter->getName());
        }
        $type = $parameter->getType();
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            throw new InvalidContainer('Dependency requires a named object type in '.$service.'::$'.$parameter->getName().'. Use an explicit factory.');
        }
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $method = $parameter->getDeclaringFunction();
            if (!$method instanceof ReflectionMethod) {
                throw new InvalidContainer('Dependencies must belong to a constructor or static factory.');
            }

            return new Argument($this->namedType($type, $method, $provider));
        }
        if ($parameter->isDefaultValueAvailable()) {
            $value = $parameter->getDefaultValue();
            Literal::emit($value);

            return new Argument(null, $value);
        }

        throw new InvalidContainer('Dependency needs a named object type or a literal default in '.$service.'::$'.$parameter->getName().'. Use an explicit factory.');
    }

    /**
     * @param class-string $provider
     * @return class-string
     */
    private function namedType(ReflectionNamedType $type, ReflectionMethod $method, string $provider): string
    {
        $name = $type->getName();
        if ($name === 'static') {
            return $provider;
        }
        if ($name === 'self') {
            return $method->getDeclaringClass()->getName();
        }
        if ($name === 'parent') {
            $parent = $method->getDeclaringClass()->getParentClass();
            if ($parent === false) {
                throw new InvalidContainer('A parent type requires a parent class.');
            }

            return $parent->getName();
        }

        return self::className($name);
    }

    /** @return class-string */
    public static function className(string $name): string
    {
        try {
            if (!class_exists($name) && !interface_exists($name)) {
                throw new InvalidContainer('Unknown service type: '.$name);
            }

            return (new ReflectionClass($name))->getName();
        } catch (ReflectionException $error) {
            throw new InvalidContainer('Unknown service type: '.$name, previous: $error);
        }
    }

    /**
     * @param array<string, Node> $nodes
     * @param list<string> $path
     * @param array<string, true> $complete
     */
    private function visit(string $type, array $nodes, array $path, array &$complete): void
    {
        if (in_array($type, $path, true)) {
            throw new InvalidContainer('Dependency cycle: '.implode(' -> ', [...$path, $type]));
        }
        if (isset($complete[$type]) || !isset($nodes[$type])) {
            return;
        }
        foreach ($nodes[$type]->arguments as $argument) {
            if ($argument->service !== null) {
                $this->visit($argument->service, $nodes, [...$path, $type], $complete);
            }
        }
        $complete[$type] = true;
    }

    /**
     * @param array<string, Node> $nodes
     * @param list<string> $path
     */
    private function checkLifetime(string $type, array $nodes, array $path): void
    {
        $lifetime = $nodes[$type]->lifetime ?? self::BUILTINS[$type];
        if ($lifetime === Lifetime::Request) {
            throw new InvalidContainer('Application service captures request state: '.implode(' -> ', [...$path, $type]));
        }
        foreach ($nodes[$type]->arguments ?? [] as $argument) {
            if ($argument->service !== null) {
                $this->checkLifetime($argument->service, $nodes, [...$path, $type]);
            }
        }
    }
}
