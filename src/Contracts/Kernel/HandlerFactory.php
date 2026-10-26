<?php

declare(strict_types=1);

namespace Phex\Contracts\Kernel;

use Phex\Contracts\Http\Handler;
use Phex\Kernel\Scope\RequestScope;

interface HandlerFactory
{
    public function create(RequestScope $scope): Handler;
}
