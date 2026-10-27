<?php

declare(strict_types=1);

use App\Http\Action\GreetAction;
use App\Http\Action\HomeAction;
use Phex\Container\Definition\EndpointRoute;

return [
    new EndpointRoute('GET', '/', HomeAction::class),
    new EndpointRoute('POST', '/greet', GreetAction::class),
];
