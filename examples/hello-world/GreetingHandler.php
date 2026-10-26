<?php

declare(strict_types=1);

namespace Phex\Examples\HelloWorld;

use Phex\Contracts\Http\Handler;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final class GreetingHandler implements Handler
{
    public function handle(Request $request): Response
    {
        $names = $request->headers->values('X-Name');
        $name = $names[0] ?? 'world';

        return new Response(body: json_encode(['hello' => $name], JSON_THROW_ON_ERROR));
    }
}
