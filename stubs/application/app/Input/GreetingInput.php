<?php

declare(strict_types=1);

namespace App\Input;

final readonly class GreetingInput
{
    public function __construct(public string $name) {}
}
