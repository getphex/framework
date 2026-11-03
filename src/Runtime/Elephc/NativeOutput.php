<?php

/**
 * Writes framework response headers and buffered bytes through ElephC's native web output.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Elephc;

use Phex\Contracts\Runtime\ResponseOutput;
use Phex\Http\Message\Headers;
use Phex\Runtime\Http\SapiHeaders;

/** The default ElephC web worker buffers output until the handler returns. */
final class NativeOutput implements ResponseOutput
{
    public function start(int $status, Headers $headers): void
    {
        SapiHeaders::send($status, $headers);
    }

    public function write(string $body): void
    {
        echo $body;
    }
}
