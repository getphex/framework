<?php

/**
 * Composes request logging, input-error handling and routing for the persistent SQLite
 * CRUD example.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\Database;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Routing\EndpointResolver;
use Phex\Contracts\Routing\Matcher;
use Phex\Http\Input\InputErrorMiddleware;
use Phex\Http\Logging\RequestLoggingMiddleware;
use Phex\Http\Middleware\Pipeline;
use Phex\Routing\Router;

final class Handlers
{
    public static function create(Matcher $matcher, EndpointResolver $resolver, RequestLoggingMiddleware $logging): Handler
    {
        return Pipeline::wrap($logging, Pipeline::wrap(new InputErrorMiddleware(), new Router($matcher, $resolver)));
    }
}
