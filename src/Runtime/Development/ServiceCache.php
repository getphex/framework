<?php

/**
 * Constructs interpreted services with scoped caching, recursion guards and deterministic
 * resource registration.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Development;

use LogicException;
use Phex\Build\Graph\Node;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Lifetime\ApplicationScope;
use Phex\Contracts\Kernel\ScopeResource;
use Phex\Kernel\Scope\RequestScope;

/** @internal PHP-only resolution of existing classes, using bootstrap-validated metadata. */
final class ServiceCache
{
    /** @var array<string, object> */
    private array $values = [];

    /** @var array<string, true> */
    private array $building = [];

    public function __construct(private readonly ApplicationScope|RequestScope $owner) {}

    /** @param callable(string): object $dependency */
    public function resolve(Node $node, callable $dependency): object
    {
        $cached = $node->lifetime !== Lifetime::Transient;
        if ($cached && isset($this->values[$node->type])) {
            return $this->values[$node->type];
        }
        if ($cached && isset($this->building[$node->type])) {
            throw new LogicException('Service construction is already in progress.');
        }
        if ($cached) {
            $this->building[$node->type] = true;
        }
        try {
            $arguments = [];
            foreach ($node->arguments as $argument) {
                $arguments[] = $argument->service === null ? $argument->default : $dependency($argument->service);
            }
            $provider = $node->provider;
            $method = $node->method;
            $value = $method === null ? new $provider(...$arguments) : $provider::$method(...$arguments);
            $type = $node->type;
            if (!$value instanceof $type) {
                throw new LogicException('Provider returned an incompatible service: '.$type);
            }
            if ($value instanceof ScopeResource) {
                $this->owner->own($value);
            }
            if ($cached) {
                $this->values[$type] = $value;
            }

            return $value;
        } finally {
            unset($this->building[$node->type]);
        }
    }
}
