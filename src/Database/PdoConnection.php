<?php

declare(strict_types=1);

namespace Phex\Database;

use LogicException;
use PDO;
use PDOException;
use PDOStatement;
use Phex\Contracts\Database\Connection;
use Throwable;

final class PdoConnection implements Connection
{
    private PDO $pdo;
    private bool $closed = false;
    private bool $leased = false;

    private function __construct(ConnectionConfig $config)
    {
        $this->pdo = new PDO($config->dsn, $config->username, $config->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_STRINGIFY_FETCHES => false,
        ]);
    }

    public static function connect(ConnectionConfig $config): PdoConnection
    {
        try {
            return new PdoConnection($config);
        } catch (PDOException $error) {
            throw new DatabaseError('Database connection failed.', 0, $error);
        }
    }

    public function acquire(): void
    {
        if ($this->closed) {
            throw new LogicException('The database connection is closed.');
        }
        if ($this->leased || $this->pdo->inTransaction()) {
            throw new LogicException('The database connection already belongs to an active session.');
        }
        $this->leased = true;
    }

    public function release(): void
    {
        $this->assertLeased();
        if ($this->closed) {
            throw new LogicException('The database connection is closed.');
        }
        try {
            if ($this->pdo->inTransaction() && !$this->pdo->rollBack()) {
                throw new DatabaseError('Database rollback failed.');
            }
        } catch (Throwable $error) {
            unset($this->pdo);
            $this->closed = true;
            throw new DatabaseError('Database rollback failed.', 0, $error);
        } finally {
            $this->leased = false;
        }
    }

    public function execute(string $sql, Parameters $parameters): int
    {
        return $this->query($sql, $parameters, 0)->affected;
    }

    public function fetchOne(string $sql, Parameters $parameters): ?Row
    {
        $result = $this->query($sql, $parameters, 1);
        if ($result->rows === []) {
            return null;
        }

        return self::typedRow($result->rows[0]);
    }

    public function fetchAll(string $sql, Parameters $parameters): array
    {
        return $this->query($sql, $parameters, 2)->rows;
    }

    public function lastInsertId(): string
    {
        $this->assertLeased();
        if ($this->closed) {
            throw new LogicException('The database connection is closed.');
        }
        try {
            $id = $this->pdo->lastInsertId();
            if ($id === false) {
                throw new DatabaseError('The database driver did not return an identity.');
            }

            return $id;
        } catch (PDOException $error) {
            throw new DatabaseError('Database identity lookup failed.', 0, $error);
        }
    }

    public function beginTransaction(): void
    {
        $this->assertLeased();
        if ($this->closed) {
            throw new LogicException('The database connection is closed.');
        }
        try {
            if (!$this->pdo->beginTransaction()) {
                throw new DatabaseError('Database transaction could not start.');
            }
        } catch (PDOException $error) {
            throw new DatabaseError('Database transaction could not start.', 0, $error);
        }
    }

    public function commit(): void
    {
        $this->assertLeased();
        if ($this->closed) {
            throw new LogicException('The database connection is closed.');
        }
        try {
            if (!$this->pdo->commit()) {
                throw new DatabaseError('Database commit failed.');
            }
        } catch (PDOException $error) {
            throw new DatabaseError('Database commit failed.', 0, $error);
        }
    }

    public function rollBack(): void
    {
        $this->assertLeased();
        if ($this->closed) {
            throw new LogicException('The database connection is closed.');
        }
        try {
            if (!$this->pdo->rollBack()) {
                throw new DatabaseError('Database rollback failed.');
            }
        } catch (PDOException $error) {
            throw new DatabaseError('Database rollback failed.', 0, $error);
        }
    }

    public function inTransaction(): bool
    {
        $this->assertLeased();
        if ($this->closed) {
            throw new LogicException('The database connection is closed.');
        }

        return $this->pdo->inTransaction();
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        try {
            if ($this->pdo->inTransaction() && !$this->pdo->rollBack()) {
                throw new DatabaseError('Database connection cleanup failed.');
            }
        } catch (PDOException $error) {
            throw new DatabaseError('Database connection cleanup failed.', 0, $error);
        } finally {
            unset($this->pdo);
            $this->closed = true;
            $this->leased = false;
        }
    }

    private function query(string $sql, Parameters $parameters, int $mode): QueryResult
    {
        $this->assertLeased();
        if ($this->closed) {
            throw new LogicException('The database connection is closed.');
        }
        if (trim($sql) === '') {
            throw new LogicException('Database SQL cannot be empty.');
        }
        try {
            $statement = $this->pdo->prepare($sql);
        } catch (PDOException $error) {
            throw new DatabaseError('Database statement failed.', 0, $error);
        }
        if (!$statement instanceof PDOStatement) {
            throw new DatabaseError('Database statement preparation failed.');
        }
        try {
            $this->bind($statement, $parameters);
            if (!$this->executeStatement($statement)) {
                throw new DatabaseError('Database execution failed.');
            }
            if ($mode === 0) {
                return new QueryResult($statement->rowCount());
            }
            $rows = [];
            while (true) {
                $values = $statement->fetch(PDO::FETCH_ASSOC);
                if ($values === false) {
                    break;
                }
                $rows[] = self::row($values);
                if ($mode === 1) {
                    break;
                }
            }

            return new QueryResult(0, $rows);
        } catch (PDOException $error) {
            throw new DatabaseError('Database statement failed.', 0, $error);
        } finally {
            try {
                if (!$statement->closeCursor()) {
                    throw new DatabaseError('Database cursor cleanup failed.');
                }
            } catch (PDOException $error) {
                throw new DatabaseError('Database cursor cleanup failed.', 0, $error);
            }
        }
    }

    private function executeStatement(PDOStatement $statement): bool
    {
        // ElephC 9419f7ac needs this typed receiver boundary after parameter binding.
        return $statement->execute();
    }

    private function bind(PDOStatement $statement, Parameters $parameters): void
    {
        foreach ($parameters->values as $key => $value) {
            $name = is_int($key) ? $key + 1 : ':'.$key;
            if ($value === null) {
                $bound = $statement->bindValue($name, null, PDO::PARAM_NULL);
            } elseif (is_bool($value)) {
                $bound = $statement->bindValue($name, (bool) $value, PDO::PARAM_BOOL);
            } elseif (is_int($value)) {
                $bound = $statement->bindValue($name, (int) $value, PDO::PARAM_INT);
            } else {
                $bound = $statement->bindValue($name, (string) $value, PDO::PARAM_STR);
            }
            if (!$bound) {
                throw new DatabaseError('Database parameter binding failed.');
            }
        }
    }

    private static function row(mixed $values): Row
    {
        if (!is_array($values)) {
            throw new DatabaseError('The database driver returned an invalid row.');
        }

        return new Row((array) $values);
    }

    private static function typedRow(mixed $row): Row
    {
        if (!$row instanceof Row) {
            throw new DatabaseError('The database driver returned an invalid row.');
        }

        return $row;
    }

    private function assertLeased(): void
    {
        if (!$this->leased) {
            throw new LogicException('The database connection has no active session.');
        }
    }
}
