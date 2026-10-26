<?php

declare(strict_types=1);

namespace Phex\Container\Lifetime;

use Phex\Contracts\Kernel\ScopeResource;
use Phex\Kernel\Scope\ResourceScope;
use Throwable;

final class ApplicationScope
{
    private readonly ResourceScope $resources;

    public function __construct()
    {
        $this->resources = new ResourceScope();
    }

    /**
     * @template TResource of ScopeResource
     * @param TResource $resource
     * @return TResource
     */
    public function own(ScopeResource $resource): ScopeResource
    {
        return $this->resources->own($resource);
    }

    public function isClosed(): bool
    {
        return $this->resources->isClosed();
    }

    public function close(?Throwable $failure = null): void
    {
        $this->resources->close($failure);
    }
}
