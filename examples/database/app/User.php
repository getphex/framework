<?php

/**
 * Carries a database user's identity and name and maps that DTO to explicit JSON fields.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\Database;

final readonly class User
{
    public function __construct(public int $id, public string $name) {}

    /** @return array<string, int|string> */
    public function payload(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
