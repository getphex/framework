<?php

/**
 * Provides request-owned database operations, generic DTO mapping and transaction
 * boundaries over a leased connection.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Database;

use LogicException;
use Phex\Contracts\Database\Connection;
use Phex\Contracts\Kernel\ScopeResource;
use Phex\Kernel\Scope\CleanupErrors;
use Throwable;

final class Session implements ScopeResource
{
    private bool $closed = false;
    private bool $managed = false;

    public function __construct(private readonly Connection $driver)
    {
        $this->driver->acquire();
    }

    public function execute(string $sql, Parameters $parameters = new Parameters()): int
    {
        $this->assertOpen();

        return $this->driver->execute($sql, $parameters);
    }

    /**
     * @template T of object
     * @param callable(Row): T $mapper
     * @return T|null
     */
    public function fetchOne(string $sql, callable $mapper, Parameters $parameters = new Parameters()): ?object
    {
        $this->assertOpen();
        $row = $this->driver->fetchOne($sql, $parameters);

        return $row === null ? null : $mapper($row);
    }

    /**
     * @template T of object
     * @param callable(Row): T $mapper
     * @return array<T>
     * @phpstan-return list<T>
     */
    public function fetchAll(string $sql, callable $mapper, Parameters $parameters = new Parameters()): array
    {
        $this->assertOpen();
        $results = [];
        foreach ($this->driver->fetchAll($sql, $parameters) as $row) {
            $results[] = $mapper(self::row($row));
        }

        return $results;
    }

    public function lastInsertId(): string
    {
        $this->assertOpen();

        return $this->driver->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->assertUnmanaged();
        if ($this->driver->inTransaction()) {
            throw new LogicException('Nested database transactions are not supported.');
        }
        $this->driver->beginTransaction();
    }

    public function commit(): void
    {
        $this->assertUnmanaged();
        $this->driver->commit();
    }

    public function rollBack(): void
    {
        $this->assertUnmanaged();
        $this->driver->rollBack();
    }

    public function inTransaction(): bool
    {
        $this->assertOpen();

        return $this->driver->inTransaction();
    }

    /** @param callable(Session): void $operation */
    public function transaction(callable $operation): void
    {
        $this->beginTransaction();
        $this->managed = true;
        try {
            $operation($this);
            $this->driver->commit();
        } catch (Throwable $error) {
            $cleanup = new CleanupErrors();
            try {
                if ($this->driver->inTransaction()) {
                    $this->driver->rollBack();
                }
            } catch (Throwable $rollbackFailure) {
                $cleanup->add($rollbackFailure);
            }
            $cleanup->throwIfAny($error);
            throw $error;
        } finally {
            $this->managed = false;
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->assertUnmanaged();
        $this->closed = true;
        $this->driver->release();
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new LogicException('The database session is closed.');
        }
    }

    private function assertUnmanaged(): void
    {
        $this->assertOpen();
        if ($this->managed) {
            throw new LogicException('The transaction callback owns its transaction boundary.');
        }
    }

    private static function row(mixed $row): Row
    {
        if (!$row instanceof Row) {
            throw new MappingError('The database driver returned an invalid row.');
        }

        return $row;
    }
}
