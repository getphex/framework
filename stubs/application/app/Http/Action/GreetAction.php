<?php

/**
 * Decodes a typed greeting request and returns the skeleton's JSON greeting response.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace App\Http\Action;

use App\Input\GreetingDecoder;
use App\Input\GreetingInput;
use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Json\JsonBody;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final readonly class GreetAction implements Endpoint
{
    public function __construct(private GreetingDecoder $decoder) {}

    public function handle(Request $request, Parameters $parameters): Response
    {
        $input = JsonBody::decode($request, fn (string $body): GreetingInput => $this->decoder->decode($body));

        return JsonResponse::create(['hello' => $input->name]);
    }
}
