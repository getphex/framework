<?php

/**
 * Carries an endpoint service type for resolution inside the current request scope.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Container\Routing;

use LogicException;
use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

/** @internal A compiled route target, resolved only after the route matches. */
final readonly class EndpointReference implements Endpoint
{
    /** @param class-string<Endpoint> $type */
    public function __construct(public string $type) {}

    public function handle(Request $request, Parameters $parameters): Response
    {
        throw new LogicException('A compiled route requires a request endpoint resolver.');
    }
}
