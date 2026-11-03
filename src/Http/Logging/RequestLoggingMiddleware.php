<?php

/**
 * Logs request completion or failure through the request-scoped logger around downstream
 * handling.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Logging;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Http\Middleware;
use Phex\Contracts\Logging\Logger;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Logging\Level;
use Phex\Logging\Record;

final readonly class RequestLoggingMiddleware implements Middleware
{
    public function __construct(private Logger $logger) {}

    public function process(Request $request, Handler $next): Response
    {
        $response = $next->handle($request);
        $this->logger->log(new Record(Level::Info, 'http.request.completed', ['status' => $response->status]));

        return $response;
    }
}
