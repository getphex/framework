<?php

declare(strict_types=1);

namespace Phex\Http\Message;

/** @internal */
final class Token
{
    public static function isValid(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        for ($index = 0; $index < strlen($value); $index++) {
            $byte = ord($value[$index]);

            if (($byte >= 48 && $byte <= 57)
                || ($byte >= 65 && $byte <= 90)
                || ($byte >= 97 && $byte <= 122)
                || str_contains('!#$%&\'*+-.^_`|~', $value[$index])) {
                continue;
            }

            return false;
        }

        return true;
    }
}
