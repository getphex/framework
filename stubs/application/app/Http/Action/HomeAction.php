<?php

declare(strict_types=1);

namespace App\Http\Action;

use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final class HomeAction implements Endpoint
{
    public function handle(Request $request, Parameters $parameters): Response
    {
        return JsonResponse::create(['hello' => 'world']);
    }
}
