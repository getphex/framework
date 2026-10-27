<?php

declare(strict_types=1);

use Phex\Build\Compiler\ApplicationConfig;
use Phex\Container\Definition\DefinitionLoader;

return new ApplicationConfig(DefinitionLoader::container(__DIR__.'/services.php'), dirname(__DIR__));
