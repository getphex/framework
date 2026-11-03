<?php

/**
 * Builds validated application settings from typed environment configuration at
 * application startup.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

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
