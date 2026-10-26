<?php

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class CycleB
{
    public function __construct(public CycleA $next) {}
}
