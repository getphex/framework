<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Http\Handler;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final class InvalidJsonResponseHandler implements Handler
{
    public function handle(Request $request): Response
    {
        return JsonResponse::create(['value' => INF]);
    }
}
