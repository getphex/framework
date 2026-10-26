<?php

declare(strict_types=1);

namespace Phex\Contracts\Routing;

interface EndpointResolver
{
    public function resolve(Endpoint $endpoint): Endpoint;
}
