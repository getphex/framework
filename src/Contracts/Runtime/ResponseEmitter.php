<?php

/**
 * Defines emission of a buffered framework response with request-method-aware body
 * handling.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Runtime;

use Phex\Http\Message\Response;

interface ResponseEmitter
{
    public function emit(Response $response, string $requestMethod): void;
}
