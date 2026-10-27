<?php

declare(strict_types=1);

namespace App\Config;

use Phex\Config\Environment;

final class SettingsFactory
{
    public static function create(Environment $environment): ApplicationSettings
    {
        return new ApplicationSettings(
            $environment->nonEmptyString('PHEX_GREETING', 'world'),
            $environment->integer('PHEX_PAGE_SIZE', 20, min: 1, max: 100),
            $environment->boolean('PHEX_UPPERCASE_GREETING', false),
        );
    }
}
