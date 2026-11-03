<?php

/**
 * Throws while mapping a row so tests can verify that statement cursors have already been
 * released.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Database;

use Phex\Contracts\Database\RowMapper;
use Phex\Database\Row;
use RuntimeException;

/** @implements RowMapper<Item> */
final class FailingMapper implements RowMapper
{
    public function map(Row $row): Item
    {
        throw new RuntimeException('mapper failure');
    }
}
