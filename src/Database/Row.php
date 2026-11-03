<?php

/**
 * Exposes immutable database columns through strict scalar and nullable mapping accessors.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Database;

final readonly class Row
{
    /** @var array<int|string, bool|float|int|string|null> */
    private array $values;

    /** @param array<int|string, mixed> $values */
    public function __construct(array $values)
    {
        $fields = [];
        foreach ($values as $name => $value) {
            $fields[$name] = self::scalar($value);
        }
        $this->values = $fields;
    }

    public function value(string $column): bool|float|int|string|null
    {
        if (!array_key_exists($column, $this->values)) {
            throw new MappingError('The database row is missing a required column.');
        }

        return $this->values[$column];
    }

    public function string(string $column): string
    {
        $value = $this->value($column);
        if (!is_string($value)) {
            throw new MappingError('The database column must be a string.');
        }

        return $value;
    }

    public function integer(string $column): int
    {
        $value = $this->value($column);
        if (!is_int($value)) {
            throw new MappingError('The database column must be an integer.');
        }

        return $value;
    }

    public function boolean(string $column): bool
    {
        $value = $this->value($column);
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        throw new MappingError('The database column must be a boolean or zero/one integer.');
    }

    public function float(string $column): float
    {
        $value = $this->value($column);
        if (!is_int($value) && !is_float($value)) {
            throw new MappingError('The database column must be numeric.');
        }

        return (float) $value;
    }

    public function nullableString(string $column): ?string
    {
        return $this->value($column) === null ? null : $this->string($column);
    }

    public function nullableInteger(string $column): ?int
    {
        return $this->value($column) === null ? null : $this->integer($column);
    }

    private static function scalar(mixed $value): bool|float|int|string|null
    {
        if ($value !== null && !is_bool($value) && !is_int($value) && !is_float($value) && !is_string($value)) {
            throw new MappingError('Database rows must contain scalar values or null.');
        }

        return $value;
    }
}
