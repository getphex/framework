<?php

/**
 * Builds a failing request dependency to exercise partial construction through a static
 * factory.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

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
