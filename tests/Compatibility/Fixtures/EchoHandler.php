<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Http\Handler;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final class EchoHandler implements Handler
{
    public int $calls = 0;

    public function handle(Request $request): Response
    {
        $this->calls++;

        return new Response(200, new Headers([
            'X-Request' => $request->headers->values('X-Request'),
            'X-Trace' => $request->headers->values('X-Trace'),
        ]), $request->body);
    }
}
