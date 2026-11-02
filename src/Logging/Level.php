<?php

declare(strict_types=1);

namespace Phex\Logging;

use InvalidArgumentException;

enum Level: string
{
    case Debug = 'debug';
    case Info = 'info';
    case Notice = 'notice';
    case Warning = 'warning';
    case Error = 'error';
    case Critical = 'critical';
    case Alert = 'alert';
    case Emergency = 'emergency';

    public static function severity(string $level): int
    {
        return match ($level) {
            'debug' => 0,
            'info' => 1,
            'notice' => 2,
            'warning' => 3,
            'error' => 4,
            'critical' => 5,
            'alert' => 6,
            'emergency' => 7,
            default => throw new InvalidArgumentException('Invalid log level.'),
        };
    }
}
