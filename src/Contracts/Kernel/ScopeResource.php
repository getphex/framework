<?php

declare(strict_types=1);

namespace Phex\Contracts\Kernel;

interface ScopeResource
{
    public function close(): void;
}
