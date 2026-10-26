<?php

declare(strict_types=1);

namespace Phex\Container\Definition;

/** @template-covariant T of object */
final readonly class Service
{
    /**
     * @param class-string<T> $type
     * @param class-string $provider
     */
    private function __construct(
        public string $type,
        public string $provider,
        public ?string $method,
        public Lifetime $lifetime,
    ) {}

    /**
     * @template TService of object
     * @param class-string<TService> $type
     * @param class-string<TService>|null $implementation
     * @return Service<TService>
     */
    public static function constructor(string $type, Lifetime $lifetime, ?string $implementation = null): Service
    {
        return new Service($type, $implementation ?? $type, null, $lifetime);
    }

    /**
     * @template TService of object
     * @param class-string<TService> $type
     * @param class-string $factory
     * @return Service<TService>
     */
    public static function factory(string $type, Lifetime $lifetime, string $factory, string $method): Service
    {
        return new Service($type, $factory, $method, $lifetime);
    }
}
