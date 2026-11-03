<?php

/**
 * Handles lookup, update and deletion of one user identified by a validated route
 * parameter.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\Database;

use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Json\JsonBody;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final readonly class UserEndpoint implements Endpoint
{
    public function __construct(private UserRepository $users) {}

    public function handle(Request $request, Parameters $parameters): Response
    {
        $id = $parameters->integer('id');
        if ($request->method === 'DELETE') {
            return $this->users->delete($id) ? new Response(status: 204) : new Response(status: 404, body: 'Not Found');
        }
        if ($request->method === 'PUT') {
            $input = JsonBody::decode($request, fn (string $body): UserInput => UserInput::decode($body));
            $user = $this->users->update($id, $input->name);
        } else {
            $user = $this->users->find($id);
        }

        return $user === null ? new Response(status: 404, body: 'Not Found') : JsonResponse::create($user->payload());
    }
}
