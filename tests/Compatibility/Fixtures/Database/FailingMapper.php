<?php

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
