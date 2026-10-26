<?php

declare(strict_types=1);

namespace Phex\Contracts\Routing;

use Phex\Http\Message\Request;
use Phex\Routing\Matcher\MatchResult;

interface Matcher
{
    public function match(Request $request): MatchResult;
}
