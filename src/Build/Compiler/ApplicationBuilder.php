<?php

declare(strict_types=1);

namespace Phex\Build\Compiler;

use Phex\Build\Analysis\InvalidContainer;
use Phex\Build\Emitter\Literal;

final class ApplicationBuilder
{
    /** @return array<string, bool> Generated paths and whether their content changed. */
    public function build(ApplicationConfig $config): array
    {
        $root = realpath($config->root);
        if ($root === false || !is_dir($root)) {
            throw new InvalidContainer('Application root does not exist: '.$config->root);
        }
        if ($config->container->handler === null && $config->container->routes === []) {
            throw new InvalidContainer('A web application needs routes or a handler.');
        }
        $directory = $root.'/var/generated';
        $artifact = $directory.'/ApplicationContainer.php';
        $source = (new ContainerCompiler())->compile($config->container, $config->className);
        $files = (new StaticSources())->collect($root, $config->sources);
        $artifacts = [$artifact => $source];
        foreach (['php' => 'Fpm', 'native' => 'Elephc'] as $target => $adapter) {
            $bootstrap = "<?php\n\ndeclare(strict_types=1);\n\n// Generated static declarations. Rebuild after structural changes.\n";
            $runtime = dirname(__DIR__, 2).'/Runtime/'.$adapter.'/bootstrap.php';
            foreach ([$runtime, ...$files, $artifact] as $file) {
                $bootstrap .= 'require_once __DIR__.'.Literal::emit('/'.RelativePath::between($directory, $file)).";\n";
            }
            if (token_get_all($bootstrap, TOKEN_PARSE) === []) {
                throw new InvalidContainer('The generated bootstrap is empty.');
            }
            $artifacts[$directory.'/'.$target.'-bootstrap.php'] = $bootstrap;
        }
        $changed = [];
        foreach ($artifacts as $file => $contents) {
            $changed[$file] = ArtifactWriter::write($file, $contents);
        }

        return $changed;
    }
}
