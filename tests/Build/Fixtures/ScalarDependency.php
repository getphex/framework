<?php

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class ScalarDependency
{
    public function __construct(public string $value) {}
}
