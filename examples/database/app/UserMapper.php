<?php

/**
 * Maps strict database row columns into the CRUD example's immutable user DTO.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\Database;

use Phex\Contracts\Database\RowMapper;
use Phex\Database\Row;

/** @implements RowMapper<User> */
final class UserMapper implements RowMapper
{
    public function map(Row $row): User
    {
        return new User($row->integer('id'), $row->string('name'));
    }
}
