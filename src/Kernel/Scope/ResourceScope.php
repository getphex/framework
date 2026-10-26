<?php

declare(strict_types=1);

namespace Phex\Kernel\Scope;

use LogicException;
use Phex\Contracts\Kernel\ScopeResource;
use Throwable;

/** @internal Shared ownership engine for application and request scopes. */
final class ResourceScope
{
    /** @var list<ScopeResource> */
    private array $resources = [];

    private bool $closed = false;

    /**
     * @template TResource of ScopeResource
     * @param TResource $resource
     * @return TResource
     */
    public function own(ScopeResource $resource): ScopeResource
    {
        if ($this->closed) {
            throw new LogicException('Cannot register resources in a closed resource scope.');
        }

        foreach ($this->resources as $existing) {
            if ($resource === $existing) {
                return $resource;
            }
        }

        $this->resources[] = $resource;

        return $resource;
    }

    public function isClosed(): bool
    {
        return $this->closed;
    }

    public function close(?Throwable $requestFailure = null): void
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $resources = $this->resources;
        $this->resources = [];

        if ($resources === []) {
            return;
        }

        $errors = new CleanupErrors();

        for ($index = count($resources) - 1; $index >= 0; $index--) {
            $resource = self::resource($resources[$index]);

            try {
                $resource->close();
            } catch (Throwable $error) {
                $errors->add($error);
            }
        }

        $errors->throwIfAny($requestFailure);
    }

    private static function resource(mixed $value): ScopeResource
    {
        // ElephC may box objects read from a stored PHP array.
        if (!$value instanceof ScopeResource) {
            throw new LogicException('Invalid resource in the resource scope.');
        }

        return $value;
    }
}
