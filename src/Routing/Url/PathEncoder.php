<?php

/**
 * Percent-encodes path segments byte by byte and rejects values unsafe for named-route
 * references.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Routing\Url;

/** @internal Byte-exact RFC 3986 segment encoding shared by build and runtime. */
final class PathEncoder
{
    public static function encode(string $value): string
    {
        $encoded = '';
        $hex = '0123456789ABCDEF';
        for ($index = 0; $index < strlen($value); $index++) {
            $byte = ord($value[$index]);
            if ($byte < 32 || $byte === 127) {
                throw new UrlGenerationFailed('URL segments cannot contain control bytes.');
            }
            $unreserved = ($byte >= 65 && $byte <= 90) || ($byte >= 97 && $byte <= 122)
                || ($byte >= 48 && $byte <= 57) || str_contains('-._~', $value[$index]);
            $encoded .= $unreserved ? $value[$index] : '%'.$hex[intdiv($byte, 16)].$hex[$byte % 16];
        }

        return $encoded;
    }
}
