<?php

/**
 * Introduces an intermediate request-session dependency to test indirect lifetime capture
 * detection.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class Bridge
{
    public function __construct(public \Phex\Examples\CompiledContainer\RequestSession $session) {}
}
