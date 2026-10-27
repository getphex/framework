<?php

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

use Phex\Contracts\Routing\Endpoint;
use Phex\Contracts\Routing\UrlGenerator;
use Phex\Http\Json\JsonBody;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final readonly class CreateUserEndpoint implements Endpoint
{
    public function __construct(private UserService $users, private CreateUserDecoder $decoder, private UrlGenerator $urls) {}

    public function handle(Request $request, Parameters $parameters): Response
    {
        $input = JsonBody::decode($request, fn (string $body): CreateUserInput => $this->decoder->decode($body));
        $user = $this->users->create($input);

        return JsonResponse::create($user->toArray(), 201, new Headers([
            'Location' => [$this->urls->generate('users.show', ['id' => $user->id])],
            'X-Request-Id' => [$this->users->session->id],
        ]));
    }
}
