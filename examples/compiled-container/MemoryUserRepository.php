<?php

/**
 * Stores example users in application memory and exposes cleanup for container ownership
 * tests.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

use Phex\Contracts\Kernel\ScopeResource;

final class MemoryUserRepository implements UserRepository, ScopeResource
{
    public int $closes = 0;
    private int $nextId = 1;
    /** @var array<int, string> */
    private array $names = [];

    public function __construct(public readonly Connection $connection) {}

    public function name(int $id): string
    {
        return (string) ($this->names[$id] ?? 'user-'.$id);
    }

    public function create(string $name): CreatedUser
    {
        $id = $this->nextId++;
        $this->names[$id] = $name;

        return new CreatedUser($id, $name);
    }

    public function close(): void
    {
        $this->closes++;
    }
}
