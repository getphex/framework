<?php

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class ReferenceDependency
{
    public function __construct(public Missing &$value) {}
}
