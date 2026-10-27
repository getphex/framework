<?php

declare(strict_types=1);

namespace App\Input;

final readonly class SearchInput
{
    public function __construct(
        public int $page,
        public int $limit,
        public bool $active,
        public string $sort,
    ) {}
}
