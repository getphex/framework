<?php

declare(strict_types=1);

use App\Http\Action\GreetAction;
use App\Http\Action\HomeAction;
use App\Http\Handlers;
use App\Input\GreetingDecoder;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\DefinitionLoader;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Contracts\Http\Handler;

return new Container([
    Service::constructor(HomeAction::class, Lifetime::Transient),
    Service::constructor(GreetAction::class, Lifetime::Transient),
    Service::constructor(GreetingDecoder::class, Lifetime::Application),
    Service::factory(Handler::class, Lifetime::Request, Handlers::class, 'create'),
], routes: DefinitionLoader::routes(__DIR__.'/../routes/web.php'), handler: Handler::class);
