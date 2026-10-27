<?php

declare(strict_types=1);

namespace Phex\Http\Json;

use Phex\Http\Message\Headers;
use Phex\Http\Message\Response;

final class JsonResponse
{
    /** @param array<int|string, mixed> $data Explicitly mapped output, without DTO introspection. */
    public static function create(array $data, int $status = 200, Headers $headers = new Headers()): Response
    {
        $body = json_encode($data, JSON_THROW_ON_ERROR, 64);
        return new Response($status, $headers->with('Content-Type', ['application/json']), $body);
    }
}
