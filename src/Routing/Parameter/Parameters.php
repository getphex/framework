<?php

declare(strict_types=1);

namespace Phex\Routing\Parameter;

use InvalidArgumentException;
use OutOfBoundsException;

final readonly class Parameters
{
    /** @var array<string, string> */
    private array $values;

    /** @param array<string, string> $values */
    public function __construct(array $values = [])
    {
        $copy = [];

        foreach ($values as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new InvalidArgumentException('Route parameter names and values must be strings.');
            }

            $copy[$name] = $value;
        }

        $this->values = $copy;
    }

    public function has(string $name): bool
    {
        return isset($this->values[$name]);
    }

    public function get(string $name): string
    {
        if (!$this->has($name)) {
            throw new OutOfBoundsException('Unknown route parameter: '.$name);
        }

        return (string) $this->values[$name];
    }

    /** @return array<string, string> */
    public function all(): array
    {
        return $this->values;
    }

    public function integer(string $name): int
    {
        $raw = $this->get($name);

        if ($raw === '') {
            throw new InvalidParameter($name);
        }

        $negative = $raw[0] === '-';
        $start = $negative ? 1 : 0;

        if (strlen($raw) === $start) {
            throw new InvalidParameter($name);
        }

        for ($index = $start; $index < strlen($raw); $index++) {
            $byte = ord($raw[$index]);

            if ($byte < 48 || $byte > 57) {
                throw new InvalidParameter($name);
            }
        }

        $digits = ltrim(substr($raw, $start), '0');

        if ($digits === '') {
            return 0;
        }

        $limit = $negative ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;

        if (strlen($digits) > strlen($limit)
            || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            throw new InvalidParameter($name);
        }

        return (int) ($negative ? '-'.$digits : $digits);
    }

    /**
     * @template T
     * @param callable(string): T $converter
     * @return T
     */
    public function map(string $name, callable $converter): mixed
    {
        return $converter($this->get($name));
    }
}
