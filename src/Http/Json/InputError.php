<?php

declare(strict_types=1);

namespace Phex\Http\Json;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class InputError extends RuntimeException
{
    /** @param array<string, string> $errors */
    private function __construct(
        public readonly int $status,
        public readonly string $error,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($error, previous: $previous);
    }

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
        if ($errors === []) {
            throw new InvalidArgumentException('Input validation needs at least one error.');
        }

        return new InputError(422, 'invalid_input', $errors);
    }
}
