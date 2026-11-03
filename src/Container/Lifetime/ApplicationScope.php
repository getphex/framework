<?php

/**
 * Owns application resources and closes them once in reverse registration order.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

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
        $this->resources->register($resource);

        return $resource;
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
