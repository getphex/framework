<?php

declare(strict_types=1);

namespace Phex\Http\Query;

final readonly class QueryParameter
{
    public function __construct(public string $name, public string $value) {}
}
