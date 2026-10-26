<?php

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class UnionDependency
{
    public function __construct(public Missing|CycleA $value) {}
}
