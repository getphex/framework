<?php

declare(strict_types=1);

namespace Phex\Runtime\Fpm;

use Phex\Contracts\Runtime\RequestReader;
use Phex\Http\Message\Request;
use RuntimeException;

final class GlobalsRequestReader implements RequestReader
{
    public function read(): Request
    {
        $body = file_get_contents('php://input');
        if ($body === false) {
            throw new RuntimeException('Cannot read the HTTP request body.');
        }

        return ServerRequest::create($_SERVER, $body);
    }
}
