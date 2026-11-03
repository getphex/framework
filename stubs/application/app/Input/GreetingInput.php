<?php

/**
 * Carries the validated name supplied to the skeleton's greeting action.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace App\Input;

final readonly class GreetingInput
{
    public function __construct(public string $name) {}
}
