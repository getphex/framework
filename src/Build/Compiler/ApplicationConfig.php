<?php

declare(strict_types=1);

namespace Phex\Build\Compiler;

use Phex\Container\Definition\Container;

final readonly class ApplicationConfig
{
    /** @param array<string, string> $sources PSR-4 prefixes and project-relative shared source directories. */
    public function __construct(
        public Container $container,
        public string $root,
        public string $className = 'App\\Generated\\ApplicationContainer',
        public array $sources = ['App\\' => 'app'],
    ) {}
}
