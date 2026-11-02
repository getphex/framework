<?php

declare(strict_types=1);

namespace Phex\Examples\Database;

use LogicException;
use Phex\Database\Parameters;
use Phex\Database\Row;
use Phex\Database\Session;

final readonly class UserRepository
{
    public function __construct(private Session $database, private UserMapper $mapper) {}

    public function find(int|string $id): ?User
    {
        return $this->database->fetchOne('SELECT id, name FROM users WHERE id = :id',
            fn (Row $row): User => $this->mapper->map($row), new Parameters(['id' => $id]));
    }

    /**
     * @return array<User>
     * @phpstan-return list<User>
     */
    public function all(): array
    {
        return $this->database->fetchAll('SELECT id, name FROM users ORDER BY id', fn (Row $row): User => $this->mapper->map($row));
    }

    public function create(string $name): User
    {
        $this->database->beginTransaction();
        $this->database->execute('INSERT INTO users (name) VALUES (:name)', new Parameters(['name' => $name]));
        $user = $this->find($this->database->lastInsertId());
        if ($user === null) {
            throw new LogicException('The inserted user is missing.');
        }
        $this->database->commit();

        return $user;
    }

    public function update(int $id, string $name): ?User
    {
        $this->database->beginTransaction();
        $this->database->execute('UPDATE users SET name = :name WHERE id = :id', new Parameters(['name' => $name, 'id' => $id]));
        $user = $this->find($id);
        $this->database->commit();

        return $user;
    }

    public function delete(int $id): bool
    {
        $this->database->beginTransaction();
        $deleted = $this->database->execute('DELETE FROM users WHERE id = :id', new Parameters(['id' => $id])) > 0;
        $this->database->commit();

        return $deleted;
    }
}
