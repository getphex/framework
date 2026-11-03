<?php

/**
 * Represents a validated dependency reference or default argument supplied to a service
 * provider.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Graph;

/** @internal A resolved service reference or a validated literal default value. */
final readonly class Argument
{
    public function __construct(public ?string $service, public mixed $default = null) {}
}
