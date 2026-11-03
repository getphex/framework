<?php

/**
 * Carries representative scalar and nullable database fields for typed binding and mapping
 * tests.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Database;

final readonly class Item
{
    public function __construct(public int $id, public string $name, public bool $active, public float $amount, public ?string $note) {}
}
