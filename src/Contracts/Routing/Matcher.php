<?php

/**
 * Defines route matching independently of interpreted tables or generated decision trees.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Routing;

use Phex\Http\Message\Request;
use Phex\Routing\Matcher\MatchResult;

interface Matcher
{
    public function match(Request $request): MatchResult;
}
