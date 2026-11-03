<?php

/**
 * Defines resolution of endpoint references against dependencies in the current request
 * context.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Routing;

interface EndpointResolver
{
    public function resolve(Endpoint $endpoint): Endpoint;
}
