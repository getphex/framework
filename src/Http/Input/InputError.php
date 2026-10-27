<?php

declare(strict_types=1);

namespace Phex\Http\Input;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class InputError extends RuntimeException
{
    /** @param array<string, string> $errors */
    protected function __construct(
        public readonly int $status,
        public readonly string $error,
        public readonly array $errors = [],
        ?Throwable $previous = null,
    ) {
        if ($status === 422 && $errors === []) {
            throw new InvalidArgumentException('Input validation needs at least one error.');
        }
        parent::__construct($error, previous: $previous);
    }

    public static function malformedQuery(): InputError
    {
        return new InputError(400, 'malformed_query');
    }

    public static function queryLimitExceeded(): InputError
    {
        return new InputError(400, 'query_limit_exceeded');
    }

    /** @param array<string, string> $errors */
    public static function validation(array $errors): InputError
    {
        return new InputError(422, 'invalid_input', $errors);
    }
}
