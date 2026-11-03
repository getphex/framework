<?php

/**
 * Describes application sources, generated container output and optional native server
 * configuration.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Phex\Build\Compiler\ApplicationConfig;
use Phex\Container\Definition\DefinitionLoader;
use Phex\Build\Config\NativeServerConfig;

return new ApplicationConfig(DefinitionLoader::container(__DIR__.'/services.php'), dirname(__DIR__),
    server: NativeServerConfig::fromFile(__DIR__.'/server.php'));
