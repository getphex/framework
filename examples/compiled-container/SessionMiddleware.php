<?php

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

use LogicException;
use Phex\Contracts\Http\Handler;
use Phex\Contracts\Http\Middleware;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final readonly class SessionMiddleware implements Middleware
{
    public function __construct(private RequestSession $session) {}

    public function process(Request $request, Handler $next): Response
    {
        if ($this->session->closes !== 0) {
            throw new LogicException('Session was closed before handling the request.');
        }

        $response = $next->handle($request);

        return $response->withHeaders($response->headers->with('X-Middleware-Id', [$this->session->id]));
    }
}
