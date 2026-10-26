<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Container;

use Fiber;

final class YieldingService
{
    public static function create(): YieldingService
    {
        Fiber::suspend();

        return new YieldingService();
    }
}
