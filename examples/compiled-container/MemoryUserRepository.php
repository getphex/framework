<?php

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

use Phex\Contracts\Kernel\ScopeResource;

final class MemoryUserRepository implements UserRepository, ScopeResource
{
    public int $closes = 0;

    public function __construct(public readonly Connection $connection) {}

    public function name(int $id): string
    {
        return 'user-'.$id;
    }

    public function close(): void
    {
        $this->closes++;
    }
}
