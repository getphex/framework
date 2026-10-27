<?php

declare(strict_types=1);

namespace Phex\Routing\Parameter;

use OutOfBoundsException;
use Phex\Support\IntegerParser;

final readonly class Parameters
{
    /** @var array<string, string> */
    private array $values;

    /** @param array<string, string> $values */
    public function __construct(array $values = [])
    {
        $copy = [];

        foreach ($values as $name => $value) {
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
        $value = IntegerParser::parse($this->get($name));
        if ($value === null) {
            throw new InvalidParameter($name);
        }

        return $value;
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
