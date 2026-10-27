<?php

declare(strict_types=1);

namespace App\Http;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Routing\EndpointResolver;
use Phex\Contracts\Routing\Matcher;
use Phex\Http\Input\InputErrorMiddleware;
use Phex\Http\Middleware\Pipeline;
use Phex\Routing\Router;

final class Handlers
{
    public static function create(Matcher $matcher, EndpointResolver $resolver): Handler
    {
        return Pipeline::wrap(new InputErrorMiddleware(), new Router($matcher, $resolver));
    }
}
