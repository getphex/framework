<?php

/**
 * Returns a fixed route so endpoint dispatch can be tested independently of route-search
 * behavior.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Routing\Matcher;
use Phex\Http\Message\Request;
use Phex\Routing\Definition\Route;
use Phex\Routing\Matcher\MatchResult;
use Phex\Routing\Parameter\Parameters;

final readonly class FixedMatcher implements Matcher
{
    public function __construct(private Route $route) {}

    public function match(Request $_request): MatchResult
    {
        return MatchResult::found($this->route, new Parameters(['id' => '42']));
    }
}
