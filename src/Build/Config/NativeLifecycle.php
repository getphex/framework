<?php

declare(strict_types=1);

namespace Phex\Build\Config;

enum NativeLifecycle: string
{
    case Request = 'request';
    case Application = 'application';
}
