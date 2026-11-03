<?php

/**
 * Validates JSON request media types and preserves a decoder's concrete DTO through a
 * generic callable bridge.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Json;

use Phex\Http\Message\Request;

final class JsonBody
{
    /**
     * @template T of object
     * @param callable(string): T $decoder
     * @return T
     */
    public static function decode(Request $request, callable $decoder): object
    {
        $types = $request->headers->values('Content-Type');
        if (count($types) !== 1) {
            throw InputError::unsupportedMediaType();
        }
        $media = strtolower(trim(explode(';', $types[0])[0]));
        if (!self::isJsonMedia($media)) {
            throw InputError::unsupportedMediaType();
        }

        return $decoder($request->body);
    }

    private static function isJsonMedia(string $media): bool
    {
        if (!str_starts_with($media, 'application/')) {
            return false;
        }
        $subtype = substr($media, strlen('application/'));
        if ($subtype === 'json') {
            return true;
        }
        if (!str_ends_with($subtype, '+json') || strlen($subtype) > 127) {
            return false;
        }
        $name = substr($subtype, 0, -5);
        if ($name === '') {
            return false;
        }
        for ($index = 0; $index < strlen($name); $index++) {
            $byte = ord($name[$index]);
            $alphanumeric = ($byte >= 97 && $byte <= 122) || ($byte >= 48 && $byte <= 57);
            if (!$alphanumeric && ($index === 0 || !str_contains('!#$&^_.+-', $name[$index]))) {
                return false;
            }
        }

        return true;
    }
}
