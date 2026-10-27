<?php

declare(strict_types=1);

namespace Phex\Console;

use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveCallbackFilterIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Throwable;

final class ProjectCreator
{
    public function create(string $target, bool $local = false): string
    {
        if (file_exists($target) || is_link($target)) {
            throw new InvalidArgumentException('The project destination already exists: '.$target);
        }
        $parent = realpath(dirname($target));
        $name = basename($target);
        if ($parent === false || !is_dir($parent) || $name === '' || $name === '.' || $name === '..') {
            throw new InvalidArgumentException('The project destination needs an existing parent directory.');
        }
        $target = $parent.DIRECTORY_SEPARATOR.$name;
        $package = dirname(__DIR__, 2);
        $template = $package.'/stubs/application';
        $created = [];
        $directories = [];
        try {
            if (!mkdir($target)) {
                throw new RuntimeException('Cannot create project directory: '.$target);
            }
            $directories[] = $target;
            $entries = new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($template, RecursiveDirectoryIterator::SKIP_DOTS), static function (SplFileInfo $item) use ($template): bool {
                $relative = substr($item->getPathname(), strlen($template) + 1);
                $first = explode(DIRECTORY_SEPARATOR, $relative)[0];

                return !in_array($first, ['vendor', 'var', '.git', '.phpunit.cache', 'composer.lock'], true);
            });
            $iterator = new RecursiveIteratorIterator($entries, RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $item) {
                if (!$item instanceof SplFileInfo) {
                    throw new RuntimeException('Invalid template entry.');
                }
                $relative = substr($item->getPathname(), strlen($template) + 1);
                if ($item->isLink()) {
                    throw new RuntimeException('Application templates cannot contain symlinks.');
                }
                $destination = $target.DIRECTORY_SEPARATOR.$relative;
                if ($item->isDir()) {
                    if (!mkdir($destination)) {
                        throw new RuntimeException('Cannot create template directory: '.$relative);
                    }
                    $directories[] = $destination;
                } else {
                    $created[] = $destination;
                    if (!copy($item->getPathname(), $destination)) {
                        throw new RuntimeException('Cannot copy template file: '.$relative);
                    }
                }
            }
            if ($local) {
                $manifest = file_get_contents($target.'/composer.json');
                if ($manifest === false) {
                    throw new RuntimeException('Cannot read application Composer configuration.');
                }
                $composer = json_decode($manifest, true, 64, JSON_THROW_ON_ERROR);
                if (!is_array($composer)) {
                    throw new RuntimeException('Invalid application Composer configuration.');
                }
                $composer['repositories'] = [[
                    'type' => 'path', 'url' => $package,
                    'options' => ['symlink' => false, 'versions' => ['getphex/phex' => '0.1.0']],
                ]];
                $source = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
                if (file_put_contents($target.'/composer.json', $source) !== strlen($source)) {
                    throw new RuntimeException('Cannot write local Composer configuration.');
                }
            }

            return $target;
        } catch (Throwable $error) {
            foreach (array_reverse($created) as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
            foreach (array_reverse($directories) as $path) {
                if (is_dir($path)) {
                    rmdir($path);
                }
            }
            throw $error;
        }
    }
}
