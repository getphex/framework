<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Http\Handler;
use Phex\Examples\CompiledContainer\CreateUserDecoder;
use Phex\Examples\CompiledContainer\CreateUserInput;
use Phex\Http\Json\JsonBody;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final class JsonHandler implements Handler
{
    public function handle(Request $request): Response
    {
        $decoder = new CreateUserDecoder();
        $input = JsonBody::decode($request, fn (string $body): CreateUserInput => $decoder->decode($body));

        return JsonResponse::create(['name' => $input->name]);
    }
}
