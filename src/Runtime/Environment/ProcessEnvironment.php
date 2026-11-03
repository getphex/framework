<?php

/**
 * Reads process-local configuration values through the shared environment reader contract.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

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
