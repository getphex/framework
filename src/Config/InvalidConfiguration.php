<?php

declare(strict_types=1);

namespace Phex\Config;

use RuntimeException;

final class InvalidConfiguration extends RuntimeException
{
    public function __construct(public readonly string $variable, string $reason)
    {
        parent::__construct('Invalid configuration for '.$variable.': '.$reason);
    }
}
