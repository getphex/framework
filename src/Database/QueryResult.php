<?php

/**
 * Carries buffered row values or an affected-row count after the driver's statement cursor
 * has closed.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Database;

/** @internal Driver values cross the boundary after the PDO cursor has closed. */
final readonly class QueryResult
{
    /** @param list<Row> $rows */
    public function __construct(public int $affected, public array $rows = []) {}
}
