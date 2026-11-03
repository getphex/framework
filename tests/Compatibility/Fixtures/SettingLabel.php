<?php

/**
 * Carries a typed configuration conversion result for generic environment mapping tests.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

final readonly class SettingLabel
{
    public function __construct(public string $value) {}
}
