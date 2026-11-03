<?php

/**
 * Defines deterministic cleanup for resources owned by application or request scopes.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Kernel;

interface ScopeResource
{
    public function close(): void;
}
