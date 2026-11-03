<?php

/**
 * Produces an unencodable JSON response to exercise server-error propagation after input
 * handling.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

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
