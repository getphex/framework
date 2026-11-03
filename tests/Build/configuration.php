<?php

/**
 * Declares configuration fixtures used to verify eager validation and build/runtime value
 * separation.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use App\Config\ApplicationSettings;
use App\Config\SettingsFactory;
use Phex\Build\Compiler\BuildConfig;
use Phex\Config\Environment;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Contracts\Config\EnvironmentReader;
use Phex\Contracts\Http\Handler;
use Phex\Tests\Compatibility\Fixtures\Config\ConfigFactories;
use Phex\Tests\Compatibility\Fixtures\Config\ConfiguredHandler;

return new BuildConfig(new Container([
    Service::factory(EnvironmentReader::class, Lifetime::Application, ConfigFactories::class, 'reader'),
    Service::constructor(Environment::class, Lifetime::Application),
    Service::factory(ApplicationSettings::class, Lifetime::Application, SettingsFactory::class, 'create'),
    Service::constructor(Handler::class, Lifetime::Request, ConfiguredHandler::class),
], ['settings' => ApplicationSettings::class, 'reader' => EnvironmentReader::class], handler: Handler::class),
    'Phex\\Tests\\Generated\\ConfigurationContainer',
    __DIR__.'/../../var/generated/tests/ConfigurationContainer.php',
);
