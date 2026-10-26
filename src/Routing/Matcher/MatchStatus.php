<?php

declare(strict_types=1);

namespace Phex\Routing\Matcher;

enum MatchStatus
{
    case Found;
    case NotFound;
    case MethodNotAllowed;
    case BadRequest;
}
