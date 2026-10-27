<?php

declare(strict_types=1);

namespace Phex\Contracts\Container;

use Phex\Contracts\Kernel\HandlerFactory;
use Phex\Contracts\Kernel\ScopeResource;

interface ApplicationContainer extends HandlerFactory, ScopeResource {}
