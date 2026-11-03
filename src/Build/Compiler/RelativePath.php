<?php

/**
 * Computes relative include paths so generated application bootstraps survive relocation.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Compiler;

use InvalidArgumentException;

/** @internal Relative include paths keep generated bootstraps relocatable. */
final class RelativePath
{
    public static function between(string $directory, string $file): string
    {
        $from = explode('/', trim(str_replace('\\', '/', $directory), '/'));
        $to = explode('/', trim(str_replace('\\', '/', $file), '/'));
        if (PHP_OS_FAMILY === 'Windows' && strtolower($from[0]) !== strtolower($to[0])) {
            throw new InvalidArgumentException('Build sources must reside on the same drive.');
        }
        while ($from !== [] && $to !== [] && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        return str_repeat('../', count($from)).implode('/', $to);
    }
}
