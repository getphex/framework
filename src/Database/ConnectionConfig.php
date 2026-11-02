<?php

declare(strict_types=1);

namespace Phex\Database;

use InvalidArgumentException;

final readonly class ConnectionConfig
{
    public function __construct(public string $dsn, public ?string $username = null, public ?string $password = null)
    {
        if ($dsn === '' || !str_contains($dsn, ':') || str_contains($dsn, "\0")) {
            throw new InvalidArgumentException('Invalid database DSN.');
        }
    }
}
