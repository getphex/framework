<?php

declare(strict_types=1);

namespace Phex\Database;

/** @internal Driver values cross the boundary after the PDO cursor has closed. */
final readonly class QueryResult
{
    /** @param list<Row> $rows */
    public function __construct(public int $affected, public array $rows = []) {}
}
