<?php

/**
 * Reads and validates typed environment values, defaults and bounds through an injected
 * reader.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Config;

use InvalidArgumentException;
use Phex\Contracts\Config\EnvironmentReader;
use Phex\Support\IntegerParser;

/** Typed access for application-scoped configuration factories. */
final readonly class Environment
{
    public function __construct(private EnvironmentReader $reader) {}

    public function string(string $name, ?string $default = null): string
    {
        $value = $this->read($name);
        if ($value !== null) {
            return $value;
        }
        if ($default !== null) {
            return $default;
        }

        throw new InvalidConfiguration($name, 'A value is required.');
    }

    public function nonEmptyString(string $name, ?string $default = null): string
    {
        $value = $this->string($name, $default);
        if ($value === '') {
            throw new InvalidConfiguration($name, 'Expected a nonempty string.');
        }

        return $value;
    }

    public function integer(string $name, ?int $default = null, ?int $min = null, ?int $max = null): int
    {
        if ($min !== null) {
            if ($max !== null && $min > $max) {
                throw new InvalidArgumentException('Configuration bounds must be ordered.');
            }
        }
        $raw = $this->read($name);
        if ($raw === null) {
            if ($default !== null) {
                return self::bounded($name, $default, $min, $max);
            }

            throw new InvalidConfiguration($name, 'A value is required.');
        }
        $value = IntegerParser::parse($raw);
        if ($value === null) {
            throw new InvalidConfiguration($name, 'Expected a signed decimal integer.');
        }

        return self::bounded($name, $value, $min, $max);
    }

    public function boolean(string $name, ?bool $default = null): bool
    {
        $raw = $this->read($name);
        if ($raw === null) {
            if ($default !== null) {
                return $default;
            }

            throw new InvalidConfiguration($name, 'A value is required.');
        }
        if ($raw === 'true' || $raw === '1') {
            return true;
        }
        if ($raw === 'false' || $raw === '0') {
            return false;
        }

        throw new InvalidConfiguration($name, 'Expected true, false, 1 or 0.');
    }

    /**
     * @template T
     * @param callable(string): T $converter
     * @return T
     */
    public function map(string $name, callable $converter): mixed
    {
        return $converter($this->string($name));
    }

    private function read(string $name): ?string
    {
        if ($name === '') {
            throw new InvalidArgumentException('Environment variable names cannot be empty.');
        }
        for ($index = 0; $index < strlen($name); $index++) {
            $byte = ord($name[$index]);
            $letter = ($byte >= 65 && $byte <= 90) || ($byte >= 97 && $byte <= 122) || $byte === 95;
            if (!$letter && !($index > 0 && $byte >= 48 && $byte <= 57)) {
                throw new InvalidArgumentException('Invalid environment variable name.');
            }
        }

        return $this->reader->get($name);
    }

    private static function bounded(string $name, int $value, ?int $min, ?int $max): int
    {
        if ($min !== null && $value < $min) {
            throw new InvalidConfiguration($name, 'Value is below the allowed minimum.');
        }
        if ($max !== null && $value > $max) {
            throw new InvalidConfiguration($name, 'Value is above the allowed maximum.');
        }

        return $value;
    }
}
