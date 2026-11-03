<?php

/**
 * Discovers application declarations and orders their dependencies for explicit runtime
 * includes.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Compiler;

use Phex\Build\Analysis\InvalidContainer;
use Phex\Build\Analysis\Analyzer;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionException;
use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use SplFileInfo;

/** @internal Build-host PSR-4 discovery; only literal includes reach the runtime. */
final class StaticSources
{
    /** @var array<string, list<ReflectionClass<object>>> */
    private array $files = [];
    /** @var array<string, true> */
    private array $complete = [];
    /** @var list<string> */
    private array $ordered = [];

    /**
     * @param array<string, string> $sources
     * @return list<string>
     */
    public function collect(string $root, array $sources): array
    {
        $this->files = [];
        $this->complete = [];
        $this->ordered = [];
        foreach ($sources as $prefix => $path) {
            if ($prefix === '' || !str_ends_with($prefix, '\\')) {
                throw new InvalidContainer('Static sources need a nonempty PSR-4 namespace prefix.');
            }
            $directory = realpath($root.'/'.$path);
            if ($directory === false || !is_dir($directory) || !str_starts_with($directory.DIRECTORY_SEPARATOR, $root.DIRECTORY_SEPARATOR)) {
                throw new InvalidContainer('Static source directories must be inside the application: '.$path);
            }
            $paths = [];
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $item) {
                if ($item instanceof SplFileInfo && $item->isFile() && $item->getExtension() === 'php') {
                    $paths[] = $item->getPathname();
                }
            }
            sort($paths, SORT_STRING);
            foreach ($paths as $file) {
                $canonical = realpath($file);
                if ($canonical === false || !str_starts_with($canonical, $directory.DIRECTORY_SEPARATOR)) {
                    throw new InvalidContainer('Static source symlinks cannot leave their source directory: '.$file);
                }
                $name = $prefix.str_replace(DIRECTORY_SEPARATOR, '\\', substr($file, strlen($directory) + 1, -4));
                if (!class_exists($name) && !interface_exists($name) && !trait_exists($name)) {
                    throw new InvalidContainer('Static source must declare its autoloadable PSR-4 type: '.$name);
                }
                $class = new ReflectionClass($name);
                if ($class->getFileName() !== $canonical) {
                    throw new InvalidContainer('Static source resolves to a different file: '.$name);
                }
                $this->files[$canonical][] = $class;
            }
        }
        ksort($this->files, SORT_STRING);
        foreach (array_keys($this->files) as $file) {
            $this->visit($file, []);
        }

        return $this->ordered;
    }

    /** @param array<string, true> $visiting */
    private function visit(string $file, array $visiting): void
    {
        if (isset($this->complete[$file])) {
            return;
        }
        if (isset($visiting[$file])) {
            throw new InvalidContainer('Cyclic declaration dependencies across static source files: '.$file);
        }
        $visiting[$file] = true;
        foreach ($this->files[$file] as $class) {
            $parent = $class->getParentClass();
            $dependencies = [...$class->getInterfaces(), ...$class->getTraits()];
            if ($parent !== false) {
                $dependencies[] = $parent;
            }
            foreach ($class->getMethods() as $method) {
                try {
                    $method->getPrototype();
                } catch (ReflectionException) {
                    continue;
                }
                // PHP checks override variance while declaring the class, before an autoloader exists.
                $dependencies = [...$dependencies, ...$this->signature($method->getReturnType(), $method->getDeclaringClass())];
                foreach ($method->getParameters() as $parameter) {
                    $dependencies = [...$dependencies, ...$this->signature($parameter->getType(), $method->getDeclaringClass())];
                }
            }
            foreach ($dependencies as $dependency) {
                $path = $dependency->getFileName();
                if ($path !== false && $path !== $file && isset($this->files[$path])) {
                    $this->visit($path, $visiting);
                }
            }
        }
        $this->complete[$file] = true;
        $this->ordered[] = $file;
    }

    /**
     * @param ReflectionClass<object> $owner
     * @return list<ReflectionClass<object>>
     */
    private function signature(?ReflectionType $type, ReflectionClass $owner): array
    {
        if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
            $classes = [];
            foreach ($type->getTypes() as $member) {
                $classes = [...$classes, ...$this->signature($member, $owner)];
            }

            return $classes;
        }
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }
        $name = $type->getName();
        if ($name === 'self' || $name === 'static') {
            return [$owner];
        }
        if ($name === 'parent') {
            $parent = $owner->getParentClass();

            return $parent === false ? [] : [$parent];
        }

        return [new ReflectionClass(Analyzer::className($name))];
    }
}
