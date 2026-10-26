<?php

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class CycleA
{
    public function __construct(public CycleB $next) {}
}
