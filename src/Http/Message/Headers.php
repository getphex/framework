<?php

/**
 * Stores immutable HTTP fields with case-insensitive lookup, ordered repeated values and
 * validated updates.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Message;

use InvalidArgumentException;

final readonly class Headers
{
    /** @var array<int|string, list<string>> */
    private array $fields;

    /** @param array<int|string, list<string>> $fields */
    public function __construct(array $fields = [])
    {
        $normalized = [];

        foreach ($fields as $name => $values) {
            // PHP converts numeric string keys to integers.
            $name = (string) $name;

            if (!Token::isValid($name)) {
                throw new InvalidArgumentException('Invalid HTTP header name.');
            }

            if ($values === []) {
                throw new InvalidArgumentException('An HTTP header needs at least one value.');
            }

            $key = strtolower($name);

            if (!isset($normalized[$key])) {
                $normalized[$key] = [];
            }

            foreach ($values as $value) {
                self::validateValue($value);
                $normalized[$key][] = $value;
            }
        }

        $this->fields = $normalized;
    }

    public function has(string $name): bool
    {
        return isset($this->fields[strtolower($name)]);
    }

    /** @return list<string> */
    public function values(string $name): array
    {
        // Keep the array return type explicit when a map lookup is boxed.
        return (array) ($this->fields[strtolower($name)] ?? []);
    }

    /** @return array<int|string, list<string>> */
    public function all(): array
    {
        return $this->fields;
    }

    /** @param list<string> $values */
    public function with(string $name, array $values): self
    {
        if (!Token::isValid($name)) {
            throw new InvalidArgumentException('Invalid HTTP header name.');
        }

        $fields = $this->fields;
        $fields[strtolower($name)] = $values;

        return new self($fields);
    }

    /** @param list<string> $values */
    public function withAdded(string $name, array $values): self
    {
        return $this->with($name, array_merge($this->values($name), $values));
    }

    public function without(string $name): self
    {
        $key = strtolower($name);
        if (!isset($this->fields[$key])) {
            return $this;
        }

        $fields = $this->fields;
        unset($fields[$key]);

        return new self($fields);
    }

    private static function validateValue(string $value): void
    {
        for ($index = 0; $index < strlen($value); $index++) {
            $byte = ord($value[$index]);

            if (($byte < 32 && $byte !== 9) || $byte === 127) {
                throw new InvalidArgumentException('Invalid control character in HTTP header value.');
            }
        }
    }
}
