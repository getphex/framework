<?php

/**
 * Maps strict item columns into the database compatibility fixture's immutable DTO.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Database;

use Phex\Contracts\Database\RowMapper;
use Phex\Database\Row;

/** @implements RowMapper<Item> */
final class ItemMapper implements RowMapper
{
    public function map(Row $row): Item
    {
        return new Item($row->integer('id'), $row->string('name'), $row->boolean('active'), $row->float('amount'), $row->nullableString('note'));
    }
}
