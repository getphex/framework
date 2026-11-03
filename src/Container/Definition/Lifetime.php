<?php

/**
 * Defines application, request and transient service ownership for both container
 * backends.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Container\Definition;

enum Lifetime
{
    case Application;
    case Request;
    case Transient;
}
