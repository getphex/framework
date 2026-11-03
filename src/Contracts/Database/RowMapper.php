<?php

/**
 * Defines a generic mapping contract that turns validated database rows into concrete
 * DTOs.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Database;

use Phex\Database\Row;

/** @template-covariant T of object */
interface RowMapper
{
    /** @return T */
    public function map(Row $row): object;
}
