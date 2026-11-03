<?php

/**
 * Verifies native configuration validation, lifecycle compiler flags, standalone launcher
 * behavior and artifact cleanup.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Cases;

use InvalidArgumentException;
use Phex\Build\Compiler\ApplicationBuilder;
use Phex\Build\Compiler\ApplicationConfig;
use Phex\Build\Compiler\NativeCompiler;
use Phex\Build\Config\NativeLifecycle;
use Phex\Build\Config\NativeServerConfig;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Contracts\Http\Handler;
use Phex\Tests\Compatibility\Fixtures\EchoHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class NativeServerConfigTest extends TestCase
{
    private string $directory;
    private string $root;

    protected function setUp(): void
    {
        $this->directory = dirname(__DIR__, 3).'/var/native-server-test-'.bin2hex(random_bytes(8));
        $this->root = $this->directory."/app's $";
        mkdir($this->root.'/native', 0777, true);
        file_put_contents($this->root.'/native/main.php', '<?php');
        file_put_contents($this->root.'/native/application.php', '<?php');
    }

    protected function tearDown(): void
    {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            if ($item instanceof SplFileInfo) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
        }
        rmdir($this->directory);
    }

    /** @return list<array{string, int, int}> */
    public static function invalidSettings(): array
    {
        return [['127.0.0.1:8080', 0, 1], ['127.0.0.1:8080', 1, -1], ['', 1, 0],
            ['127.0.0.1', 1, 0], [':8080', 1, 0], ['127.0.0.1:', 1, 0],
            ['127.0.0.1:0', 1, 0], ['127.0.0.1:65536', 1, 0], ['127.0.0.1:+80', 1, 0],
            ["127.0.0.1:80\n", 1, 0], ['127.0.0.1:abc', 1, 0]];
    }

    #[DataProvider('invalidSettings')]
    public function testInvalidSettingsFailBeforeArtifactGeneration(string $listen, int $workers, int $maxRequests): void
    {
        $this->expectException(InvalidArgumentException::class);
        new NativeServerConfig(listen: $listen, workers: $workers, maxRequests: $maxRequests);
    }

    public function testTheDefaultIsRequestLifetimeAndIpv6AndUnlimitedRecyclingAreAllowed(): void
    {
        $this->assertSame(NativeLifecycle::Request, (new NativeServerConfig())->lifecycle);
        $config = new NativeServerConfig(listen: '[::1]:65535', workers: 1, maxRequests: 0);
        $this->assertSame('[::1]:65535', $config->listen);
        $this->assertSame(0, $config->maxRequests);
    }

    public function testConfigurationFilesMustReturnTheTypedObject(): void
    {
        $path = $this->directory.'/server.php';
        file_put_contents($path, '<?php return [];');
        $this->expectException(InvalidArgumentException::class);
        NativeServerConfig::fromFile($path);
    }

    public function testRequestCompilationKeepsTheOriginalFlagsAndWritesASuccessManifest(): void
    {
        $binary = (new NativeCompiler($this->compiler(false)))->compile($this->config(new NativeServerConfig()));
        $this->assertFileExists($binary);
        $this->assertSame('request'."\n", file_get_contents($binary.'.lifecycle'));
        $this->assertSame(['--web', '--strict-php', $binary.'.php'], json_decode(self::contents(dirname($binary).'/argv.json'), true));
        $this->assertStringContainsString("native/main.php", self::contents($binary.'.php'));
    }

    public function testApplicationCompilationChoosesTheApplicationEntryAndCapabilityFlags(): void
    {
        $binary = (new NativeCompiler($this->compiler(true)))->compile($this->config(new NativeServerConfig(NativeLifecycle::Application)));
        $this->assertSame(['--web', '--strict-php', '--web-isolation=worker', '--web-lifecycle=application', $binary.'.php'], json_decode(self::contents(dirname($binary).'/argv.json'), true));
        $this->assertSame('application'."\n", file_get_contents($binary.'.lifecycle'));
        $this->assertStringContainsString('native/application.php', self::contents($binary.'.php'));
    }

    public function testAnUnsupportedCompilerCannotLeaveAStaleSuccessfulBuild(): void
    {
        mkdir($this->root.'/var/build', 0777, true);
        foreach (['server', 'server.s', 'server.lifecycle'] as $name) {
            file_put_contents($this->root.'/var/build/'.$name, 'stale');
        }
        try {
            (new NativeCompiler($this->compiler(false)))->compile($this->config(new NativeServerConfig(NativeLifecycle::Application)));
            $this->fail('An unsupported compiler must fail.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('--web-lifecycle', $error->getMessage());
        }
        foreach (['server', 'server.s', 'server.lifecycle'] as $name) {
            $this->assertFileDoesNotExist($this->root.'/var/build/'.$name);
        }
    }

    public function testLauncherUsesDefaultsThenEnvironmentThenExplicitOptionsWithSafeArguments(): void
    {
        $binary = (new NativeCompiler($this->compiler(false)))->compile($this->config(new NativeServerConfig(listen: '127.0.0.1:8000', workers: 2, maxRequests: 7)));
        $launcher = dirname($binary).'/start';
        $this->assertTrue(is_executable($launcher));
        [$status, $output] = $this->launch($launcher);
        $this->assertSame(0, $status);
        $this->assertSame("<--listen>\n<127.0.0.1:8000>\n<--workers>\n<2>\n<--max-requests>\n<7>\n", $output);
        [$status, $output] = $this->launch($launcher, ['--workers=3', '--max-requests', '0', '--access-log'], ['PHEX_LISTEN' => '127.0.0.1:9000', 'PHEX_WORKERS' => '8']);
        $this->assertSame(0, $status);
        $this->assertSame("<--listen>\n<127.0.0.1:9000>\n<--workers>\n<3>\n<--max-requests>\n<0>\n<--access-log>\n", $output);
        $canary = '127.0.0.1:9000; touch '.$this->directory.'/unexpected';
        [$status, $output] = $this->launch($launcher, environment: ['PHEX_LISTEN' => $canary]);
        $this->assertSame(0, $status);
        $this->assertStringContainsString('<'.$canary.'>', $output);
        $this->assertFileDoesNotExist($this->directory.'/unexpected');
    }

    public function testACompilerThatProducesNothingCannotReuseAnOldExecutable(): void
    {
        mkdir($this->root.'/var/build', 0777, true);
        $binary = $this->root.'/var/build/server';
        file_put_contents($binary, 'stale');
        chmod($binary, 0755);
        $compiler = $this->directory.'/empty-compiler';
        file_put_contents($compiler, "#!/bin/sh\nexit 0\n");
        chmod($compiler, 0755);
        try {
            (new NativeCompiler($compiler))->compile($this->config(new NativeServerConfig()));
            $this->fail('An old executable must not satisfy a new build.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('did not produce', $error->getMessage());
        }
        $this->assertFileDoesNotExist($binary);
        $this->assertFileDoesNotExist($binary.'.lifecycle');
    }

    public function testRuntimeDefaultsCanBeRegeneratedWithoutChangingTheBinary(): void
    {
        $binary = (new NativeCompiler($this->compiler(false)))->compile($this->config(new NativeServerConfig()));
        $snapshot = self::contents($binary);
        (new ApplicationBuilder())->build($this->config(new NativeServerConfig(workers: 9, maxRequests: 0)));
        $this->assertSame($snapshot, self::contents($binary));
        [$status, $output] = $this->launch(dirname($binary).'/start');
        $this->assertSame(0, $status);
        $this->assertStringContainsString("<--workers>\n<9>", $output);
        (new ApplicationBuilder())->build($this->config(new NativeServerConfig(NativeLifecycle::Application)));
        [$status, $output] = $this->launch(dirname($binary).'/start');
        $this->assertSame(1, $status);
        $this->assertStringContainsString('lifecycle changed', $output);
    }

    public function testInvalidEnvironmentOverridesAreRejectedBeforeExecutingTheServer(): void
    {
        $binary = (new NativeCompiler($this->compiler(false)))->compile($this->config(new NativeServerConfig()));
        foreach ([['PHEX_WORKERS' => '0'], ['PHEX_WORKERS' => ''], ['PHEX_WORKERS' => '1.5'], ['PHEX_MAX_REQUESTS' => '-1'], ['PHEX_LISTEN' => '']] as $environment) {
            [$status] = $this->launch(dirname($binary).'/start', environment: $environment);
            $this->assertSame(1, $status, json_encode($environment, JSON_THROW_ON_ERROR));
        }
    }

    private function config(NativeServerConfig $server): ApplicationConfig
    {
        return new ApplicationConfig(new Container([
            Service::constructor(Handler::class, Lifetime::Request, EchoHandler::class),
        ], handler: Handler::class), $this->root, sources: [], server: $server);
    }

    private function compiler(bool $application): string
    {
        $path = $this->directory.'/compiler';
        $source = <<<'PHP'
#!/usr/bin/env php
<?php
if (in_array('--help', $argv, true)) {
    echo @HELP@;
    exit;
}
$source = $argv[count($argv) - 1];
$binary = substr($source, 0, -4);
file_put_contents(dirname($source).'/argv.json', json_encode(array_slice($argv, 1)));
file_put_contents($binary, "#!/bin/sh\nprintf '<%s>\\n' \"\$@\"\n");
chmod($binary, 0755);
PHP;
        file_put_contents($path, str_replace('@HELP@', var_export($application ? '--web-lifecycle MODE' : 'old compiler', true), $source));
        chmod($path, 0755);

        return $path;
    }

    /**
     * @param list<string> $arguments
     * @param array<string, string> $environment
     * @return array{int, string}
     */
    private function launch(string $launcher, array $arguments = [], array $environment = []): array
    {
        $variables = getenv();
        foreach (['PHEX_LISTEN', 'PHEX_WORKERS', 'PHEX_MAX_REQUESTS'] as $name) {
            unset($variables[$name]);
        }
        $assignments = [];
        foreach ($environment as $name => $value) {
            $assignments[] = $name.'='.$value;
        }
        // env preserves explicitly empty values that proc_open's environment mapping drops.
        $process = proc_open(['env', ...$assignments, $launcher, ...$arguments], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $this->directory, $variables);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot run the generated launcher.');
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return [proc_close($process), ($output === false ? '' : $output).($errors === false ? '' : $errors)];
    }

    private static function contents(string $path): string
    {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Missing test artifact.');
        }

        return $content;
    }
}
