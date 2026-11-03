<?php

/**
 * Associates an owning application object with a resource context inside one request
 * scope.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Kernel\Scope;

use Phex\Contracts\Kernel\ScopeResource;

/** @internal */
final readonly class ScopeContext
{
    public function __construct(public object $owner, public ScopeResource $value) {}
}
