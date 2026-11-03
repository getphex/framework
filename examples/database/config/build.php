<?php

/**
 * Defines source discovery, output class and native server settings for building the
 * SQLite CRUD application.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Phex\Build\Compiler\ApplicationConfig;
use Phex\Build\Config\NativeServerConfig;
use Phex\Container\Definition\DefinitionLoader;

return new ApplicationConfig(DefinitionLoader::container(__DIR__.'/services.php'), dirname(__DIR__),
    className: 'Phex\\Examples\\Database\\Generated\\ApplicationContainer',
    sources: ['Phex\\Examples\\Database\\' => 'app'],
    server: NativeServerConfig::fromFile(__DIR__.'/server.php'));
