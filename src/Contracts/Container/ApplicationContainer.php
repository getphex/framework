<?php

/**
 * Defines an application container that creates request handlers and owns application
 * cleanup.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Container;

use Phex\Contracts\Kernel\HandlerFactory;
use Phex\Contracts\Kernel\ScopeResource;

interface ApplicationContainer extends HandlerFactory, ScopeResource {}
