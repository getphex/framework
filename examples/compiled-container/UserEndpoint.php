<?php

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final readonly class UserEndpoint implements Endpoint
{
    public function __construct(public UserService $users) {}

    public function handle(Request $request, Parameters $parameters): Response
    {
        $name = $this->users->name($parameters->integer('id'));

        return new Response(200, new Headers(['X-Request-Id' => [$this->users->session->id]]), $name);
    }
}
