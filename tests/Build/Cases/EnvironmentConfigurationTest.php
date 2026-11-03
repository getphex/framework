<?php

/**
 * Verifies build-time configuration isolation and eager runtime settings validation in
 * both container backends.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Cases;

use App\Config\ApplicationSettings;
use Phex\Build\Compiler\BuildConfig;
use Phex\Build\Compiler\ContainerCompiler;
use Phex\Contracts\Config\EnvironmentReader;
use Phex\Http\Message\Request;
use Phex\Kernel\Application;
use Phex\Kernel\Scope\RequestScope;
use Phex\Runtime\Development\DevelopmentContainer;
use Phex\Tests\Compatibility\Fixtures\Config\ConfigFactories;
use Phex\Tests\Compatibility\Fixtures\Config\MutableEnvironment;
use PHPUnit\Framework\TestCase;

final class EnvironmentConfigurationTest extends TestCase
{
    public function testBuildDoesNotInvokeConfigurationFactoriesOrSerializeTheirValues(): void
    {
        $config = $this->config();
        $calls = ConfigFactories::$calls;
        $source = (new ContainerCompiler())->compile($config->container, 'Test\\Generated');
        $this->assertSame($calls, ConfigFactories::$calls);
        $this->assertStringContainsString('\\App\\Config\\SettingsFactory::create(', $source);
        $this->assertStringContainsString('\\Phex\\Tests\\Compatibility\\Fixtures\\Config\\ConfigFactories::reader()', $source);
        $this->assertStringNotContainsString('"first"', $source);
    }

    public function testDevelopmentAlsoValidatesEagerlyAndReusesSettingsAcrossRequests(): void
    {
        $container = new DevelopmentContainer($this->config()->container);
        $scope = new RequestScope(new Request('GET', '/'));
        try {
            $reader = $container->get(EnvironmentReader::class);
            $this->assertInstanceOf(MutableEnvironment::class, $reader);
            $this->assertSame(3, $reader->reads);
            $settings = $container->get(ApplicationSettings::class);
            $reader->values['PHEX_GREETING'] = 'changed';
            $this->assertSame($settings, $container->request($scope)->get(ApplicationSettings::class));
            $application = new Application($container);
            $this->assertSame('{"greeting":"first","size":7,"uppercase":false}', $application->handle(new Request('GET', '/'))->body);
            $this->assertSame('{"greeting":"first","size":7,"uppercase":false}', $application->handle(new Request('GET', '/'))->body);
            $this->assertSame(3, $reader->reads);
        } finally {
            $scope->close();
            $container->close();
        }
    }

    private function config(): BuildConfig
    {
        $config = require __DIR__.'/../configuration.php';
        $this->assertInstanceOf(BuildConfig::class, $config);

        return $config;
    }
}
