<?php

/**
 * Lists stored users or creates a validated user with a named resource-location response
 * header.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\Database;

use LogicException;
use Phex\Contracts\Routing\Endpoint;
use Phex\Contracts\Routing\UrlGenerator;
use Phex\Http\Json\JsonBody;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final readonly class UsersEndpoint implements Endpoint
{
    public function __construct(private UserRepository $users, private UrlGenerator $urls) {}

    public function handle(Request $request, Parameters $parameters): Response
    {
        if ($request->method === 'POST') {
            $input = JsonBody::decode($request, fn (string $body): UserInput => UserInput::decode($body));
            $user = $this->users->create($input->name);
            $response = JsonResponse::create($user->payload(), 201);

            return $response->withHeaders($response->headers->with('Location', [$this->urls->generate('users.show', ['id' => $user->id])]));
        }
        $payload = [];
        foreach ($this->users->all() as $user) {
            $payload[] = self::user($user)->payload();
        }

        return JsonResponse::create($payload);
    }

    private static function user(mixed $user): User
    {
        if (!$user instanceof User) {
            throw new LogicException('Expected a user DTO.');
        }

        return $user;
    }
}
