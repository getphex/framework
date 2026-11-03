<?php

/**
 * Defines typed middleware that can delegate to the next HTTP handler or return a response
 * directly.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Http;

use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

interface Middleware
{
    public function process(Request $request, Handler $next): Response;
}
