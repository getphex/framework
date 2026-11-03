<?php

/**
 * Owns one request's resources and identity-keyed container contexts until deterministic
 * request cleanup.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Kernel\Scope;

use LogicException;
use Phex\Contracts\Kernel\ScopeResource;
use Phex\Http\Message\Request;
use Throwable;

final class RequestScope
{
    private readonly ResourceScope $resources;

    /** @var list<ScopeContext> */
    private array $contexts = [];

    public function __construct(public readonly Request $request)
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

    /** @internal Retrieve a container-specific context without retaining request state in the container. */
    public function context(object $owner): ?ScopeResource
    {
        $this->assertOpen();
        foreach ($this->contexts as $value) {
            $context = self::typedContext($value);
            if ($context->owner === $owner) {
                return $context->value;
            }
        }

        return null;
    }

    /** @internal Bind one context per container identity for this request. */
    public function bindContext(object $owner, ScopeResource $context): void
    {
        if ($this->context($owner) !== null) {
            throw new LogicException('A context is already bound to this request scope.');
        }
        $this->resources->register($context);
        $this->contexts[] = new ScopeContext($owner, $context);
    }

    public function close(?Throwable $requestFailure = null): void
    {
        try {
            $this->resources->close($requestFailure);
        } finally {
            $this->contexts = [];
        }
    }

    private function assertOpen(): void
    {
        if ($this->isClosed()) {
            throw new LogicException('The request scope is closed.');
        }
    }

    private static function typedContext(mixed $value): ScopeContext
    {
        if (!$value instanceof ScopeContext) {
            throw new LogicException('Invalid request context.');
        }

        return $value;
    }
}
