<?php

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

use Phex\Contracts\Kernel\ScopeResource;

final class Connection implements ScopeResource
{
    public int $closes = 0;

    public function close(): void
    {
        $this->closes++;
    }
}
