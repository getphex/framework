<?php

declare(strict_types=1);

namespace Phex\Kernel\Scope;

use Phex\Contracts\Kernel\ScopeResource;

/** @internal */
final readonly class ScopeContext
{
    public function __construct(public object $owner, public ScopeResource $value) {}
}
