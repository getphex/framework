<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Container;

use Phex\Contracts\Http\Handler;
use Phex\Examples\HelloWorld\GreetingHandler;

final class BrokenFactory
{
    public static function handler(BrokenRequest $broken): Handler
    {
        return new GreetingHandler();
    }
}
