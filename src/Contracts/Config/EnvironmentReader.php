<?php

declare(strict_types=1);

namespace Phex\Contracts\Config;

interface EnvironmentReader
{
    /** Missing values are null; present empty strings remain empty. */
    public function get(string $name): ?string;
}
