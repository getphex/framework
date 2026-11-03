<?php

/**
 * Defines transport operations for starting an HTTP response and writing its body bytes.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Runtime;

use Phex\Http\Message\Headers;

/** A runtime-specific output boundary for buffered HTTP responses. */
interface ResponseOutput
{
    public function start(int $status, Headers $headers): void;

    public function write(string $body): void;
}
