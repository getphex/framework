<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Kernel\ScopeResource;
use Throwable;

final class TrackedResource implements ScopeResource
{
    public int $closes = 0;

    public function __construct(
        public readonly string $name,
        private ScopeLog $log,
        private ?Throwable $failure = null,
    ) {}

    public function close(): void
    {
        $this->closes++;
        $this->log->record($this->name.':close');

        if ($this->failure !== null) {
            throw $this->failure;
        }
    }
}
