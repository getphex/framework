<?php

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class VariadicDependency
{
    /** @var array<int|string, Missing> */
    public array $values;

    public function __construct(Missing ...$value)
    {
        $this->values = $value;
    }
}
