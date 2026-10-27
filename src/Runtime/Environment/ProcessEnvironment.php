<?php

declare(strict_types=1);

namespace Phex\Runtime\Environment;

use Phex\Contracts\Config\EnvironmentReader;

/** Read process-local values, independently of SAPI request metadata. */
final class ProcessEnvironment implements EnvironmentReader
{
    public function get(string $name): ?string
    {
        $value = getenv($name, true);
        if ($value === false) {
            return null;
        }

        return $value;
    }
}
