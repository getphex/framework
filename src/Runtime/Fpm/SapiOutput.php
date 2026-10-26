<?php

declare(strict_types=1);

namespace Phex\Runtime\Fpm;

use Phex\Contracts\Runtime\ResponseOutput;
use Phex\Http\Message\Headers;
use Phex\Runtime\Http\SapiHeaders;
use RuntimeException;

final class SapiOutput implements ResponseOutput
{
    public function start(int $status, Headers $headers): void
    {
        if (headers_sent()) {
            throw new RuntimeException('Cannot emit a response after output has started.');
        }
        SapiHeaders::send($status, $headers);
    }

    public function write(string $body): void
    {
        echo $body;
    }
}
