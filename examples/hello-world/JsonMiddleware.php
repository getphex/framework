<?php

/**
 * Adds JSON response metadata around the minimal greeting handler through typed
 * middleware.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\HelloWorld;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Http\Middleware;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final class JsonMiddleware implements Middleware
{
    public function process(Request $request, Handler $next): Response
    {
        $response = $next->handle($request);

        return $response->withHeaders($response->headers->with('Content-Type', ['application/json']));
    }
}
