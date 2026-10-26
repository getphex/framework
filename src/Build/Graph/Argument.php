<?php

declare(strict_types=1);

namespace Phex\Build\Graph;

/** @internal A resolved service reference or an emitted default literal. */
final readonly class Argument
{
    public function __construct(public ?string $service, public string $literal = '') {}
}
