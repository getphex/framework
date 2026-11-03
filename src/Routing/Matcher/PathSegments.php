<?php

/**
 * Splits incoming request paths before decoding each segment once and rejecting malformed
 * or control bytes.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Routing\Matcher;

use InvalidArgumentException;

/** @internal */
final class PathSegments
{
    /** @return list<string> */
    public static function fromUri(string $uri): array
    {
        if (!str_starts_with($uri, '/')) {
            throw new InvalidArgumentException('Routing requires an origin-form request target.');
        }

        $query = strpos($uri, '?');
        $path = $query === false ? $uri : substr($uri, 0, $query);

        if ($path === '/') {
            return [];
        }

        $segments = [];

        foreach (explode('/', substr($path, 1)) as $part) {
            for ($index = 0; $index < strlen($part); $index++) {
                if ($part[$index] === '%') {
                    if ($index + 2 >= strlen($part)
                        || !str_contains('0123456789abcdefABCDEF', $part[$index + 1])
                        || !str_contains('0123456789abcdefABCDEF', $part[$index + 2])) {
                        throw new InvalidArgumentException('Malformed percent encoding in request path.');
                    }

                    $index += 2;
                }
            }

            // ElephC 0.27.6 applies form-style decoding to raw '+'.
            $decoded = rawurldecode(str_replace('+', '%2B', $part));

            for ($index = 0; $index < strlen($decoded); $index++) {
                $byte = ord($decoded[$index]);

                if ($byte < 32 || $byte === 127) {
                    throw new InvalidArgumentException('Control character in decoded request path.');
                }
            }

            $segments[] = $decoded;
        }

        return $segments;
    }
}
