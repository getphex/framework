<?php

/**
 * Uses the current request trace while delegating to the shared router and composing the
 * response.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\RequestLifecycle;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Http\Middleware;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final readonly class ScopeMiddleware implements Middleware
{
    public function __construct(private RequestTrace $trace) {}

    public function process(Request $request, Handler $next): Response
    {
        $this->trace->assertOpen();
        $response = $next->handle($request);

        return $response->withHeaders($response->headers->with('X-Request-Id', [$this->trace->id]));
    }
}
