<?php

declare(strict_types=1);

namespace Phex\Logging;

use InvalidArgumentException;

final readonly class Record
{
    /** @param array<string, bool|float|int|string|null> $context */
    public function __construct(public Level $level, public string $message, public array $context = [])
    {
        foreach ($context as $key => $value) {
            self::validateField($key, $value);
        }
    }

    private static function validateField(mixed $key, mixed $value): void
    {
        if (!is_string($key)) {
            throw new InvalidArgumentException('Log field names must be strings.');
        }
        if ($value !== null && !is_bool($value) && !is_float($value) && !is_int($value) && !is_string($value)) {
            throw new InvalidArgumentException('Log fields must be scalar values or null.');
        }
    }
}
