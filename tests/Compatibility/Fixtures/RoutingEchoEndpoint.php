<?php

/**
 * Returns routing input and captures in a predictable form for endpoint dispatch
 * assertions.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final class RoutingEchoEndpoint implements Endpoint
{
    public function handle(Request $request, Parameters $parameters): Response
    {
        return new Response(body: $request->method.' '.implode('|', array_values($parameters->all())));
    }
}
