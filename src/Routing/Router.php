<?php

/**
 * Connects route matching, scoped endpoint resolution and HTTP routing error responses.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Routing;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Routing\Matcher;
use Phex\Contracts\Routing\EndpointResolver;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Matcher\MatchStatus;
use Phex\Routing\Parameter\InvalidParameter;

final readonly class Router implements Handler
{
    public function __construct(private Matcher $matcher, private ?EndpointResolver $resolver = null) {}

    public function handle(Request $request): Response
    {
        $result = $this->matcher->match($request);

        if ($result->status === MatchStatus::BadRequest) {
            return new Response(status: 400, body: 'Bad Request');
        }

        if ($result->status === MatchStatus::NotFound) {
            return new Response(status: 404, body: 'Not Found');
        }

        if ($result->status === MatchStatus::MethodNotAllowed) {
            return new Response(405, new Headers(['Allow' => [implode(', ', $result->allowedMethods)]]), 'Method Not Allowed');
        }

        $route = $result->route();

        try {
            if ($this->resolver !== null) {
                $endpoint = $this->resolver->resolve($route->endpoint);

                return $endpoint->handle($request, $result->parameters);
            }

            return $route->endpoint->handle($request, $result->parameters);
        } catch (InvalidParameter) {
            return new Response(status: 400, body: 'Bad Request');
        }
    }
}
