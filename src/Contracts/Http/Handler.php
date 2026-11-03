<?php

/**
 * Defines the shared request-to-response boundary used by applications, middleware and
 * routers.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Http;

use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

interface Handler
{
    public function handle(Request $request): Response;
}
