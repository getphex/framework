<?php

/**
 * Defines named-route path generation from explicit string or integer parameter values.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Routing;

interface UrlGenerator
{
    /** @param array<string, string|int> $parameters */
    public function generate(string $name, array $parameters = []): string;
}
