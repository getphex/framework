<?php

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class NeedsMissing
{
    public function __construct(public Missing $missing) {}
}
