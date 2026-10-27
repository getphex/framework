<?php

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

interface UserRepository
{
    public function name(int $id): string;

    public function create(string $name): CreatedUser;
}
