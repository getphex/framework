<?php

/**
 * Carries validated search pagination, filtering and sort options for the search action.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

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
