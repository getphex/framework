<?php

/**
 * Represents a literal or named parameter segment in a validated route pattern.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Routing\Definition;

/** @internal */
final readonly class Segment
{
    public function __construct(public string $value, public bool $parameter) {}
}
