<?php

declare(strict_types=1);

namespace Phex\Contracts\Routing;

use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

interface Endpoint
{
    public function handle(Request $request, Parameters $parameters): Response;
}
