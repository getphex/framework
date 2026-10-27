<?php

declare(strict_types=1);

namespace Phex\Config;

use Phex\Contracts\Config\EnvironmentReader;

final readonly class ArrayEnvironment implements EnvironmentReader
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

    public function get(string $name): ?string
    {
        return $this->values[$name] ?? null;
    }
}
