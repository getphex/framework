<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Http\Middleware;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final readonly class TraceMiddleware implements Middleware
{
    public function __construct(private string $label) {}

    public function process(Request $request, Handler $next): Response
    {
        $forwarded = $request->withHeaders($request->headers->withAdded('X-Trace', [$this->label]));
        $response = $next->handle($forwarded);

        return $response->withHeaders($response->headers->withAdded('X-Unwind', [$this->label]));
    }
}
