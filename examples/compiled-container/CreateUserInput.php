<?php

/**
 * Carries the validated user name supplied to the compiled-container creation action.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

final readonly class CreateUserInput
{
    public function __construct(public string $name) {}
}
