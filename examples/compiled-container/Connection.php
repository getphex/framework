<?php

/**
 * Provides a close-counting application resource to demonstrate generated container
 * ownership.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

use Phex\Contracts\Kernel\ScopeResource;

final class Connection implements ScopeResource
{
    public int $closes = 0;

    public function close(): void
    {
        $this->closes++;
    }
}
