<?php

/**
 * Declares the skeleton's named home, greeting and search endpoint routes.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use App\Http\Action\GreetAction;
use App\Http\Action\HomeAction;
use App\Http\Action\SearchAction;
use Phex\Container\Definition\EndpointRoute;

return [
    new EndpointRoute('GET', '/', HomeAction::class, name: 'home'),
    new EndpointRoute('POST', '/greet', GreetAction::class, name: 'greet'),
    new EndpointRoute('GET', '/search', SearchAction::class, name: 'search'),
];
