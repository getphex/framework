<?php

declare(strict_types=1);

namespace Phex\Examples\JsonApi;

use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final class HealthEndpoint implements Endpoint
{
    public function handle(Request $_request, Parameters $_parameters): Response
    {
        return new Response(body: '{"status":"ok"}');
    }
}
