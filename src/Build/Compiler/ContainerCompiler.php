<?php

/**
 * Combines dependency analysis, PHP emission and artifact writing for compiled containers.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Compiler;

use Phex\Build\Analysis\Analyzer;
use Phex\Build\Emitter\ContainerEmitter;
use Phex\Container\Definition\Container;

final class ContainerCompiler
{
    public function compile(Container $container, string $className): string
    {
        return (new ContainerEmitter())->emit((new Analyzer())->analyze($container), $className);
    }

    /** Write a complete, syntax-checked artifact atomically. Return false if already current. */
    public function build(BuildConfig $config): bool
    {
        return ArtifactWriter::write($config->output, $this->compile($config->container, $config->className));
    }
}
