<?php

/**
 * Returns a response without delegating so pipeline short-circuit behavior can be
 * verified.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Http\Middleware;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final class RejectMiddleware implements Middleware
{
    public function process(Request $_request, Handler $_next): Response
    {
        return new Response(status: 403, body: 'forbidden');
    }
}
