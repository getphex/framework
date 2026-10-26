<?php

declare(strict_types=1);

namespace Phex\Examples\RequestLifecycle;

use LogicException;
use Phex\Contracts\Kernel\ScopeResource;

final class RequestTrace implements ScopeResource
{
    private bool $closed = false;

    public function __construct(public readonly string $id) {}

    public function assertOpen(): void
    {
        if ($this->closed) {
            throw new LogicException('The request trace has already been released.');
        }
    }

    public function close(): void
    {
        $this->closed = true;
    }
}
