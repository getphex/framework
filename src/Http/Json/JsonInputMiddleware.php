<?php

declare(strict_types=1);

namespace Phex\Http\Json;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Http\Middleware;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final class JsonInputMiddleware implements Middleware
{
    public function process(Request $request, Handler $next): Response
    {
        try {
            return $next->handle($request);
        } catch (InputError $error) {
            if ($error->errors !== []) {
                return JsonResponse::create(['error' => $error->error, 'errors' => $error->errors], $error->status);
            }

            return JsonResponse::create(['error' => $error->error], $error->status);
        }
    }
}
