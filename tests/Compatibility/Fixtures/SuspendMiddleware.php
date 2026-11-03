<?php

/**
 * Suspends downstream execution to verify that interleaved Fiber requests retain
 * independent state.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Fiber;
use Phex\Contracts\Http\Handler;
use Phex\Contracts\Http\Middleware;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final class SuspendMiddleware implements Middleware
{
    public function process(Request $request, Handler $next): Response
    {
        Fiber::suspend($request->body);

        return $next->handle($request);
    }
}
