<?php

declare(strict_types=1);

namespace Phex\Build\Compiler;

use RuntimeException;

final class NativeCompiler
{
    public function compile(ApplicationConfig $config): string
    {
        $root = realpath($config->root);
        if ($root === false || !is_file($root.'/native/main.php')) {
            throw new RuntimeException('The application needs native/main.php.');
        }
        (new ApplicationBuilder())->build($config);
        $entry = $root.'/var/build/server.php';
        $binary = $root.'/var/build/server';
        ArtifactWriter::write($entry, "<?php\n\ndeclare(strict_types=1);\n\nrequire __DIR__.'/../../native/main.php';\n");
        $success = false;
        try {
            $process = proc_open(['elephc', '--web', '--strict-php', $entry], [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root);
            if (!is_resource($process)) {
                throw new RuntimeException('Cannot start ElephC.');
            }
            $status = proc_close($process);
            if ($status !== 0) {
                throw new RuntimeException('Native compilation failed with exit status '.$status.'.');
            }
            $success = true;
        } finally {
            if (is_file($root.'/var/build/server.s')) {
                unlink($root.'/var/build/server.s');
            }
            if (!$success && is_file($binary)) {
                unlink($binary);
            }
        }

        return $binary;
    }
}
