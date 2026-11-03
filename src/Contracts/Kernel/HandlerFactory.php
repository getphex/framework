<?php

/**
 * Defines how an application creates a handler bound to an explicit request scope.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Kernel;

use Phex\Contracts\Http\Handler;
use Phex\Kernel\Scope\RequestScope;

interface HandlerFactory
{
    public function create(RequestScope $scope): Handler;
}
