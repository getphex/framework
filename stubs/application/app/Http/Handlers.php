<?php

/**
 * Composes request logging, input-error conversion and endpoint routing for generated
 * applications.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace App\Http;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Routing\EndpointResolver;
use Phex\Contracts\Routing\Matcher;
use Phex\Http\Input\InputErrorMiddleware;
use Phex\Http\Middleware\Pipeline;
use Phex\Http\Logging\RequestLoggingMiddleware;
use Phex\Routing\Router;

final class Handlers
{
    public static function create(Matcher $matcher, EndpointResolver $resolver, RequestLoggingMiddleware $logging): Handler
    {
        return Pipeline::wrap($logging, Pipeline::wrap(new InputErrorMiddleware(), new Router($matcher, $resolver)));
    }
}
