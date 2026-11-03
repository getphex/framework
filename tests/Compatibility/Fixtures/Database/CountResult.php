<?php

/**
 * Carries an aggregate integer result for typed database query assertions.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Database;

final readonly class CountResult
{
    public function __construct(public int $value) {}
}
