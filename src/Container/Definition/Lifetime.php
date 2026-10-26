<?php

declare(strict_types=1);

namespace Phex\Container\Definition;

enum Lifetime
{
    case Application;
    case Request;
    case Transient;
}
