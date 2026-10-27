<?php

declare(strict_types=1);

namespace Phex\Build\Compiler;

use RuntimeException;

/** @internal Atomic, idempotent writes for complete build artifacts. */
final class ArtifactWriter
{
    public static function write(string $path, string $source): bool
    {
        if (is_file($path) && file_get_contents($path) === $source) {
            return false;
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create generated output directory: '.$directory);
        }
        $temporary = tempnam($directory, '.phex-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create the generated artifact.');
        }
        try {
            if (file_put_contents($temporary, $source) !== strlen($source)
                || !chmod($temporary, 0666 & ~umask()) || !rename($temporary, $path)) {
                throw new RuntimeException('Cannot write the generated artifact: '.$path);
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }

        return true;
    }
}
