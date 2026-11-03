<?php

/**
 * Runs a supplied cleanup callback to test reentrancy and failures inside resource-scope
 * closure.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Kernel\ScopeResource;
use Phex\Kernel\Scope\RequestScope;

final readonly class ScopeCallbackResource implements ScopeResource
{
    public function __construct(
        private RequestScope $scope,
        private ScopeLog $log,
        private bool $registerDuringClose = false,
    ) {}

    public function close(): void
    {
        $this->log->record($this->scope->isClosed() ? 'closed' : 'open');

        if ($this->registerDuringClose) {
            $this->scope->own(new TrackedResource('late', $this->log));
        } else {
            $this->scope->close();
        }
    }
}
