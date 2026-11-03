<?php

/**
 * Tracks request identity and explicit resource closure in the lifecycle example.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

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
