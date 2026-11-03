<?php

/**
 * Stores one decoded query name and value while preserving duplicate pairs and numeric
 * names.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Query;

final readonly class QueryParameter
{
    public function __construct(public string $name, public string $value) {}
}
