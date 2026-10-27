<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use App\Config\ApplicationSettings;
use App\Http\Action\SearchAction;
use App\Input\SearchDecoder;
use Phex\Contracts\Http\Handler;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final class QueryHandler implements Handler
{
    public function handle(Request $request): Response
    {
        return (new SearchAction(new SearchDecoder(new ApplicationSettings('world', 20, false))))->handle($request, new Parameters());
    }
}
