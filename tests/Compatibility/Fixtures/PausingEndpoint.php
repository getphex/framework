<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Fiber;
use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final class PausingEndpoint implements Endpoint
{
    public function handle(Request $request, Parameters $parameters): Response
    {
        Fiber::suspend($parameters->get('id'));

        return new Response(body: $parameters->get('id').':'.$request->body);
    }
}
