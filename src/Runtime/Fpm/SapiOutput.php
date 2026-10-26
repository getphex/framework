<?php

declare(strict_types=1);

namespace Phex\Runtime\Fpm;

use Phex\Contracts\Runtime\ResponseOutput;
use Phex\Http\Message\Headers;
use RuntimeException;

final class SapiOutput implements ResponseOutput
{
    public function start(int $status, Headers $headers): void
    {
        if (headers_sent()) {
            throw new RuntimeException('Cannot emit a response after output has started.');
        }
        foreach ($headers->all() as $name => $values) {
            foreach ($values as $value) {
                header((string) $name.': '.$value, false);
            }
        }
        // PHP treats Location specially. Restore the explicit response status after all headers.
        http_response_code($status);
    }

    public function write(string $body): void
    {
        echo $body;
    }
}
