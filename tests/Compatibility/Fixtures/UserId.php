<?php

/**
 * Carries a typed route conversion result for generic parameter-mapping assertions.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

final readonly class UserId
{
    public function __construct(public int $value) {}
}
