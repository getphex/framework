<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Database;

final readonly class CountResult
{
    public function __construct(public int $value) {}
}
