<?php

declare(strict_types=1);

namespace Phex\Examples\Database;

use Phex\Config\Environment;
use Phex\Database\ConnectionConfig;

final class DatabaseFactory
{
    public static function configuration(Environment $environment): ConnectionConfig
    {
        return new ConnectionConfig($environment->nonEmptyString('PHEX_DATABASE_DSN'));
    }
}
