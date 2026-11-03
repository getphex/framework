<?php

/**
 * Writes generated artifacts atomically and preserves identical files without rewriting
 * them.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Compiler;

use RuntimeException;

/** @internal Atomic, idempotent writes for complete build artifacts. */
final class ArtifactWriter
{
    public static function write(string $path, string $source, int $permissions = 0666): bool
    {
        if (is_file($path) && file_get_contents($path) === $source) {
            if (($permissions & 0111) !== 0 && !is_executable($path)) {
                if (!chmod($path, $permissions & ~umask())) {
                    throw new RuntimeException('Cannot make the generated artifact executable: '.$path);
                }

                return true;
            }
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
                || !chmod($temporary, $permissions & ~umask()) || !rename($temporary, $path)) {
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
