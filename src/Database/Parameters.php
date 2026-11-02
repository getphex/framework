<?php

declare(strict_types=1);

namespace Phex\Database;

use InvalidArgumentException;

final readonly class Parameters
{
    /** @var array<int|string, bool|float|int|string|null> */
    public array $values;

    /** @param array<int|string, bool|float|int|string|null> $values */
    public function __construct(array $values = [])
    {
        $positional = array_is_list($values);
        $bindings = [];
        foreach ($values as $key => $value) {
            if (is_int($key)) {
                if (!$positional) {
                    throw new InvalidArgumentException('Positional database parameters must be a zero-based list.');
                }
                self::validate($value);
                $bindings[(int) $key] = $value;
                continue;
            }
            $text = (string) $key;
            $name = str_starts_with($text, ':') ? substr($text, 1) : $text;
            if (!self::validName($name) || array_key_exists($name, $bindings)) {
                throw new InvalidArgumentException('Invalid or duplicate database parameter name.');
            }
            self::validate($value);
            $bindings[$name] = $value;
        }
        $this->values = $bindings;
    }

    private static function validName(string $name): bool
    {
        if ($name === '') {
            return false;
        }
        for ($index = 0; $index < strlen($name); $index++) {
            $byte = ord($name[$index]);
            $letter = ($byte >= 65 && $byte <= 90) || ($byte >= 97 && $byte <= 122) || $byte === 95;
            if (!$letter && !($index > 0 && $byte >= 48 && $byte <= 57)) {
                return false;
            }
        }

        return true;
    }

    private static function validate(mixed $value): void
    {
        if ($value !== null && !is_bool($value) && !is_int($value) && !is_float($value) && !is_string($value)) {
            throw new InvalidArgumentException('Database parameters must be scalar values or null.');
        }
        if (is_float($value) && !is_finite($value)) {
            throw new InvalidArgumentException('Database floating parameters must be finite.');
        }
    }
}
