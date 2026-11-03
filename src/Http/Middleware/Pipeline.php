<?php

/**
 * Assembles typed middleware around a terminal handler without keeping request traversal
 * state.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Middleware;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Http\Middleware;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final readonly class Pipeline implements Handler
{
    private function __construct(
        private Middleware $middleware,
        private Handler $next,
    ) {}

    /**
     * Explicit class names retain the owning type during ElephC specialization.
     *
     * @template TMiddleware of Middleware
     * @template TNext of Handler
     * @param TMiddleware $middleware
     * @param TNext $next
     */
    public static function wrap(Middleware $middleware, Handler $next): \Phex\Http\Middleware\Pipeline
    {
        return new \Phex\Http\Middleware\Pipeline($middleware, $next);
    }

    public function handle(Request $request): Response
    {
        return $this->middleware->process($request, $this->next);
    }
}
