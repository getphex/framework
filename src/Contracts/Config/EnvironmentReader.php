<?php

/**
 * Defines the environment lookup boundary used by typed configuration without depending on
 * process globals.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Config;

interface EnvironmentReader
{
    /** Missing values are null; present empty strings remain empty. */
    public function get(string $name): ?string;
}
