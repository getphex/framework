<?php

/**
 * Requires an unbound dependency to verify missing-service diagnostics before code
 * generation.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class NeedsMissing
{
    public function __construct(public Missing $missing) {}
}
