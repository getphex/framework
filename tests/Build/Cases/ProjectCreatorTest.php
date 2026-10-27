<?php

declare(strict_types=1);

namespace Phex\Tests\Build\Cases;

use InvalidArgumentException;
use Phex\Console\ProjectCreator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class ProjectCreatorTest extends TestCase
{
    public function testTheDefaultProjectIsIndependentAndContainsNoBuildOrDependencyArtifacts(): void
    {
        $path = dirname(__DIR__, 3).'/var/project-test-'.bin2hex(random_bytes(8));
        try {
            $this->assertSame($path, (new ProjectCreator())->create($path));
            $manifest = file_get_contents($path.'/composer.json');
            $this->assertIsString($manifest);
            $this->assertStringNotContainsString('repositories', $manifest);
            $this->assertStringContainsString('"getphex/phex": "^0.1"', $manifest);
            $this->assertFileExists($path.'/native/main.php');
            $this->assertFileExists($path.'/routes/web.php');
            $this->assertDirectoryDoesNotExist($path.'/var');
            $this->assertDirectoryDoesNotExist($path.'/vendor');
        } finally {
            self::remove($path);
        }
    }

    public function testLocalDevelopmentMirrorsOnlyTheExplicitPackageOverride(): void
    {
        $path = dirname(__DIR__, 3).'/var/project-test-'.bin2hex(random_bytes(8));
        try {
            (new ProjectCreator())->create($path, true);
            $manifest = file_get_contents($path.'/composer.json');
            $this->assertIsString($manifest);
            $this->assertStringContainsString('"type": "path"', $manifest);
            $this->assertStringContainsString('"symlink": false', $manifest);
            $this->assertStringContainsString('"getphex/phex": "0.1.0"', $manifest);
        } finally {
            self::remove($path);
        }
    }

    public function testAnExistingDestinationIsNeverOverwritten(): void
    {
        $path = dirname(__DIR__, 3).'/var/project-test-'.bin2hex(random_bytes(8));
        mkdir($path);
        file_put_contents($path.'/owned.txt', 'keep');
        try {
            try {
                (new ProjectCreator())->create($path);
                $this->fail('An existing destination must be rejected.');
            } catch (InvalidArgumentException) {
                $this->assertSame('keep', file_get_contents($path.'/owned.txt'));
                $this->assertSame(['.', '..', 'owned.txt'], scandir($path));
            }
        } finally {
            self::remove($path);
        }
    }

    private static function remove(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            if ($entry instanceof SplFileInfo) {
                if ($entry->isDir()) {
                    rmdir($entry->getPathname());
                } else {
                    unlink($entry->getPathname());
                }
            }
        }
        rmdir($path);
    }
}
