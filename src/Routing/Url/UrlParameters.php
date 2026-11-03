<?php

/**
 * Validates required and extra named URL parameters and encodes their values as individual
 * path segments.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Routing\Url;

/** @internal Shared checks used by interpreted and generated URL code. */
final class UrlParameters
{
    /**
     * @param array<string, string|int> $parameters
     * @param list<string> $names
     */
    public static function only(array $parameters, array $names): void
    {
        foreach (array_keys($parameters) as $name) {
            if (!in_array($name, $names, true)) {
                throw new UrlGenerationFailed('Unexpected URL parameter: '.$name);
            }
        }
    }

    /** @param array<string, string|int> $parameters */
    public static function segment(array $parameters, string $name): string
    {
        if (!array_key_exists($name, $parameters)) {
            throw new UrlGenerationFailed('Missing URL parameter: '.$name);
        }
        $value = self::stringValue($parameters[$name]);
        if ($value === '' || $value === '.' || $value === '..') {
            throw new UrlGenerationFailed('URL parameters must be nonempty and cannot be dot segments.');
        }

        return PathEncoder::encode($value);
    }

    private static function stringValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_int($value)) {
            return (string) $value;
        }

        throw new UrlGenerationFailed('URL parameters must be strings or integers.');
    }
}
