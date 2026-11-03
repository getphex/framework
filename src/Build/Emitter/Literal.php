<?php

/**
 * Escapes supported configuration values as PHP literals without evaluating application
 * code.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Emitter;

use Phex\Build\Analysis\InvalidContainer;

/** @internal Emit data as PHP syntax without executing it. */
final class Literal
{
    public static function emit(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value)) {
            return $value === PHP_INT_MIN ? '\\PHP_INT_MIN' : (string) $value;
        }
        if (is_float($value) && is_finite($value)) {
            return var_export($value, true);
        }
        if (is_string($value)) {
            $result = '"';
            for ($index = 0; $index < strlen($value); $index++) {
                $byte = ord($value[$index]);
                $result .= match (true) {
                    $byte < 32 || $byte > 126 => sprintf('\\x%02X', $byte),
                    $value[$index] === '"', $value[$index] === '\\', $value[$index] === '$' => '\\'.$value[$index],
                    default => $value[$index],
                };
            }

            return $result.'"';
        }
        if (is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $items[] = self::emit($key).' => '.self::emit($item);
            }

            return '['.implode(', ', $items).']';
        }

        throw new InvalidContainer('Constructor defaults must be literal scalar, null, or array values. Use an explicit factory.');
    }
}
