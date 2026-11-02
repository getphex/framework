<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Database;

use Phex\Contracts\Database\RowMapper;
use Phex\Database\Row;

/** @implements RowMapper<CountResult> */
final class CountMapper implements RowMapper
{
    public function map(Row $row): CountResult
    {
        return new CountResult($row->integer('total'));
    }
}
