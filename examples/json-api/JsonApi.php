<?php

/**
 * Assembles the JSON routing example's endpoint table and terminal HTTP handler.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\JsonApi;

use Phex\Contracts\Http\Handler;
use Phex\Examples\HelloWorld\JsonMiddleware;
use Phex\Http\Middleware\Pipeline;
use Phex\Routing\Definition\Route;
use Phex\Routing\Matcher\TableMatcher;
use Phex\Routing\Router;

final class JsonApi
{
    public static function application(): Handler
    {
        $matcher = new TableMatcher([
            new Route('GET', '/health', new HealthEndpoint()),
            new Route('GET', '/users/{id}', new UserEndpoint()),
        ]);

        return Pipeline::wrap(new JsonMiddleware(), new Router($matcher));
    }
}
