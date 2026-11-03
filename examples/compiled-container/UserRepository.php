<?php

/**
 * Defines the example's user lookup and creation boundary independently of storage
 * implementation.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

interface UserRepository
{
    public function name(int $id): string;

    public function create(string $name): CreatedUser;
}
