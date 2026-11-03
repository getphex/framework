<?php

/**
 * Defines physical database access and the exclusive lease acquired by each request
 * session.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Database;

use Phex\Contracts\Kernel\ScopeResource;
use Phex\Database\Parameters;
use Phex\Database\Row;

/** Application-owned physical connection. Acquire/release are owned by the request Session. */
interface Connection extends ScopeResource
{
    public function acquire(): void;
    public function release(): void;
    public function execute(string $sql, Parameters $parameters): int;
    public function fetchOne(string $sql, Parameters $parameters): ?Row;
    /** @return list<Row> */
    public function fetchAll(string $sql, Parameters $parameters): array;
    public function lastInsertId(): string;
    public function beginTransaction(): void;
    public function commit(): void;
    public function rollBack(): void;
    public function inTransaction(): bool;
}
