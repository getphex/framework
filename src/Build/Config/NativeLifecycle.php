<?php

/**
 * Enumerates the request and application lifetimes selectable for native web compilation.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Config;

enum NativeLifecycle: string
{
    case Request = 'request';
    case Application = 'application';
}
