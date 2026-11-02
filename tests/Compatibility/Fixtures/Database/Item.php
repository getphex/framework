<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Database;

final readonly class Item
{
    public function __construct(public int $id, public string $name, public bool $active, public float $amount, public ?string $note) {}
}
