<?php

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

final readonly class CreatedUser
{
    public function __construct(public int $id, public string $name) {}

    /** @return array{id: int, name: string} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name];
    }
}
