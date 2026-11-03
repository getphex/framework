<?php

/**
 * Supplies a by-reference constructor parameter that static container analysis must
 * reject.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class ReferenceDependency
{
    public function __construct(public Missing &$value) {}
}
