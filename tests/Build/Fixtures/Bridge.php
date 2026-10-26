<?php

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class Bridge
{
    public function __construct(public \Phex\Examples\CompiledContainer\RequestSession $session) {}
}
