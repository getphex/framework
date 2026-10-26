<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

final class ScopeLog
{
    /** @var list<string> */
    public array $events = [];

    public function record(string $event): void
    {
        $this->events[] = $event;
    }
}
