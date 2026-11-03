<?php

/**
 * Describes an application's source roots, generated container and native server build
 * settings.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Compiler;

use Phex\Container\Definition\Container;
use Phex\Build\Config\NativeServerConfig;

final readonly class ApplicationConfig
{
    /** @param array<string, string> $sources PSR-4 prefixes and project-relative shared source directories. */
    public function __construct(
        public Container $container,
        public string $root,
        public string $className = 'App\\Generated\\ApplicationContainer',
        public array $sources = ['App\\' => 'app'],
        public NativeServerConfig $server = new NativeServerConfig(),
    ) {}
}
