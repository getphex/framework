<?php

/**
 * Captures a request dependency through an intermediate service to exercise application
 * lifetime rejection.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class CaptiveRoot
{
    public function __construct(public Bridge $bridge) {}
}
