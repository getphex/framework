<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Http\Middleware;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final class RetryMiddleware implements Middleware
{
    public function process(Request $request, Handler $next): Response
    {
        $first = $next->handle($request);

        return $next->handle($request->withBody($first->body.' retried'));
    }
}
