<?php

/**
 * Specializes input failures for malformed JSON, unsupported media types and decoded field
 * validation.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Json;

use Throwable;

/** JSON factories retained on the original public exception type. */
final class InputError extends \Phex\Http\Input\InputError
{
    public static function malformed(?Throwable $previous = null): InputError
    {
        return new InputError(400, 'malformed_json', previous: $previous);
    }

    public static function unsupportedMediaType(): InputError
    {
        return new InputError(415, 'unsupported_media_type');
    }

    /** @param array<string, string> $errors */
    public static function validation(array $errors): InputError
    {
        return new InputError(422, 'invalid_input', $errors);
    }
}
