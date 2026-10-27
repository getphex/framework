<?php

declare(strict_types=1);

namespace Phex\Contracts\Routing;

interface UrlGenerator
{
    /** @param array<string, string|int> $parameters */
    public function generate(string $name, array $parameters = []): string;
}
