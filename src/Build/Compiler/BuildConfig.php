<?php

declare(strict_types=1);

namespace Phex\Build\Compiler;

use Phex\Container\Definition\Container;

final readonly class BuildConfig
{
    public function __construct(public Container $container, public string $className, public string $output) {}
}
