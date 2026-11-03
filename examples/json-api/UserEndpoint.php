<?php

/**
 * Converts a captured user ID and returns it as explicit JSON output for routing
 * demonstrations.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\JsonApi;

use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final class UserEndpoint implements Endpoint
{
    public function handle(Request $_request, Parameters $parameters): Response
    {
        return new Response(body: json_encode(['id' => $parameters->integer('id')], JSON_THROW_ON_ERROR));
    }
}
