<?php

declare(strict_types=1);

namespace App\Config;

final readonly class ApplicationSettings
{
    public function __construct(
        public string $greeting,
        public int $pageSize,
        public bool $uppercaseGreeting,
    ) {}
}
