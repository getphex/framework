<?php

/**
 * Distinguishes found routes, missing paths, rejected methods and malformed request
 * targets.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Routing\Matcher;

enum MatchStatus
{
    case Found;
    case NotFound;
    case MethodNotAllowed;
    case BadRequest;
}
