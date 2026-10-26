<?php

declare(strict_types=1);

namespace Phex\Build\Compiler;

use Phex\Build\Analysis\Analyzer;
use Phex\Build\Emitter\ContainerEmitter;
use Phex\Container\Definition\Container;
use RuntimeException;

final class ContainerCompiler
{
    public function compile(Container $container, string $className): string
    {
        return (new ContainerEmitter())->emit((new Analyzer())->analyze($container), $className);
    }

    /** Write a complete, syntax-checked artifact atomically. Return false if already current. */
    public function build(BuildConfig $config): bool
    {
        $source = $this->compile($config->container, $config->className);
        if (is_file($config->output) && file_get_contents($config->output) === $source) {
            return false;
        }
        $directory = dirname($config->output);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create generated output directory: '.$directory);
        }
        $temporary = tempnam($directory, '.phex-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create the generated artifact.');
        }
        try {
            if (file_put_contents($temporary, $source) !== strlen($source)
                || !chmod($temporary, 0666 & ~umask())
                || !rename($temporary, $config->output)) {
                throw new RuntimeException('Cannot write the generated artifact: '.$config->output);
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return true;
    }
}
