<?php

declare(strict_types=1);

namespace Phex\Contracts\Database;

use Phex\Database\Row;

/** @template-covariant T of object */
interface RowMapper
{
    /** @return T */
    public function map(Row $row): object;
}
