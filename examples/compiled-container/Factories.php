<?php

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Routing\EndpointResolver;
use Phex\Contracts\Routing\Matcher;
use Phex\Http\Message\Request;
use Phex\Http\Json\JsonInputMiddleware;
use Phex\Http\Middleware\Pipeline;
use Phex\Routing\Router;

final class Factories
{
    public static function repository(Connection $connection): UserRepository
    {
        return new MemoryUserRepository($connection);
    }

    public static function session(Request $request): RequestSession
    {
        return new RequestSession($request->headers->values('X-Request-Id')[0] ?? 'anonymous');
    }

    public static function handler(Matcher $matcher, EndpointResolver $resolver, RequestSession $session): Handler
    {
        return Pipeline::wrap(new SessionMiddleware($session), Pipeline::wrap(new JsonInputMiddleware(), new Router($matcher, $resolver)));
    }
}
