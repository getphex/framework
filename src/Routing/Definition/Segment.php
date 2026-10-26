<?php

declare(strict_types=1);

namespace Phex\Routing\Definition;

/** @internal */
final readonly class Segment
{
    public function __construct(public string $value, public bool $parameter) {}
}
