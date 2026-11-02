<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use InvalidArgumentException;
use LogicException;
use Phex\Database\ConnectionConfig;
use Phex\Database\DatabaseError;
use Phex\Database\MappingError;
use Phex\Database\Parameters;
use Phex\Database\PdoConnection;
use Phex\Database\Row;
use Phex\Database\Session;
use Phex\Kernel\Scope\RequestScope;
use Phex\Http\Message\Request;
use Phex\Tests\Compatibility\Fixtures\Database\CountMapper;
use Phex\Tests\Compatibility\Fixtures\Database\CountResult;
use Phex\Tests\Compatibility\Fixtures\Database\FailingMapper;
use Phex\Tests\Compatibility\Fixtures\Database\Item;
use Phex\Tests\Compatibility\Fixtures\Database\ItemMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DatabaseTest extends TestCase
{
    private PdoConnection $driver;
    private Session $database;

    protected function setUp(): void
    {
        $this->driver = PdoConnection::connect(new ConnectionConfig('sqlite::memory:'));
        $this->database = new Session($this->driver);
        $this->database->execute('CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER NOT NULL, amount REAL NOT NULL, note TEXT)');
    }

    protected function tearDown(): void
    {
        $this->database->close();
        $this->driver->close();
    }

    public function testPreparedBindingsPreserveValuesAndReturnTypedDtos(): void
    {
        $name = "Ada'); DROP TABLE items; --\0binary";
        $this->assertSame(1, $this->database->execute('INSERT INTO items (name, active, amount, note) VALUES (:name, :active, :amount, :note)', new Parameters([
            'name' => $name, 'active' => true, 'amount' => 1.5, 'note' => null,
        ])));
        $this->assertSame('1', $this->database->lastInsertId());
        $item = self::item($this->database->fetchOne('SELECT * FROM items WHERE id = ?', fn (Row $row): Item => (new ItemMapper())->map($row), new Parameters([1])));
        $this->assertSame($name, $item->name);
        $this->assertSame(1, $item->id);
        $this->assertTrue($item->active);
        $this->assertSame(1.5, $item->amount);
        $this->assertNull($item->note);
    }

    public function testEmptyAndMultipleResultsAndMutationsShareOneApi(): void
    {
        $this->assertNull($this->database->fetchOne('SELECT * FROM items', fn (Row $row): Item => (new ItemMapper())->map($row)));
        $this->assertSame([], $this->database->fetchAll('SELECT * FROM items', fn (Row $row): Item => (new ItemMapper())->map($row)));
        $this->insert('first');
        $this->insert('second');
        $items = $this->database->fetchAll('SELECT * FROM items ORDER BY id', fn (Row $row): Item => (new ItemMapper())->map($row));
        $itemCount = count($items);
        $this->assertSame(2, $itemCount);
        $first = self::item($items[0]);
        $second = self::item($items[1]);
        $this->assertSame('first', $first->name);
        $this->assertSame('second', $second->name);
        $this->assertFalse($first->active);
        $this->assertSame(0.0, $second->amount);
        $this->assertSame(1, $this->database->execute('UPDATE items SET name = :name WHERE id = :id', new Parameters([':name' => 'updated', ':id' => 1])));
        $this->assertSame(1, $this->database->execute('DELETE FROM items WHERE id = ?', new Parameters([2])));
        $this->assertSame(0, $this->database->execute('DELETE FROM items WHERE id = ?', new Parameters([999])));
    }

    public function testTransactionCallbackCommits(): void
    {
        $this->database->transaction(function (Session $database): void {
            $database->execute("INSERT INTO items (name, active, amount) VALUES ('committed', 0, 2.5)");
        });
        $this->assertFalse($this->database->inTransaction());
        $this->assertSame(1, $this->countItems());
    }

    public function testBindingsKeepTheirValuesWhenTheInputOrAnExportChanges(): void
    {
        $input = ['name' => 'original', 'active' => false, 'amount' => 0];
        $bindings = new Parameters($input);
        $input['name'] = 'changed input';
        $export = $bindings->values;
        $export['name'] = 'changed export';
        $this->assertSame('changed export', $export['name']);
        $this->database->execute('INSERT INTO items (name, active, amount) VALUES (:name, :active, :amount)', $bindings);
        $item = self::item($this->database->fetchOne('SELECT * FROM items', fn (Row $row): Item => (new ItemMapper())->map($row)));
        $this->assertSame('original', $item->name);
        $this->assertFalse($item->active);
    }

    public function testAnExceptionRollsBackWritesAndAllowsTheNextTransaction(): void
    {
        try {
            $this->database->transaction(function (Session $database): void {
                $written = $database->execute("INSERT INTO items (name, active, amount) VALUES ('rolled back', 1, 1)");
                if ($written > 0) {
                    throw new RuntimeException('transaction failure');
                }
            });
            $this->fail('The operation must fail.');
        } catch (RuntimeException $error) {
            $this->assertSame('transaction failure', $error->getMessage());
        }
        $this->assertSame(0, $this->countItems());
        $this->database->transaction(function (Session $database): void {
            $database->execute("INSERT INTO items (name, active, amount) VALUES ('recovered', 1, 1)");
        });
        $this->assertSame(1, $this->countItems());
    }

    public function testRequestCleanupRollsBackAnUnfinishedTransactionAndReleasesTheLease(): void
    {
        $scope = new RequestScope(new Request('GET', '/'));
        $scope->own($this->database);
        $this->database->beginTransaction();
        $this->insert('unfinished');
        $scope->close();
        $this->database = new Session($this->driver);
        $this->assertFalse($this->database->inTransaction());
        $this->assertSame(0, $this->countItems());
        $this->insert('next request');
        $this->assertSame(1, $this->countItems());
    }

    public function testExplicitCommitAndRollback(): void
    {
        $this->database->beginTransaction();
        $this->insert('committed');
        $this->database->commit();
        $this->database->beginTransaction();
        $this->insert('discarded');
        $this->database->rollBack();
        $this->assertSame(1, $this->countItems());
    }

    public function testOnlyOneRequestCanLeaseAPhysicalConnection(): void
    {
        try {
            new Session($this->driver);
            $this->fail('A second request must not share the active lease.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('active session', $error->getMessage());
        }
        $this->insert('first owner');
        $this->database->close();
        $this->database = new Session($this->driver);
        $this->assertSame(1, $this->countItems());
    }

    public function testClosedSessionsCannotBeReusedAndCloseIsIdempotent(): void
    {
        $this->database->close();
        $this->database->close();
        $this->expectException(LogicException::class);
        $this->database->execute('SELECT 1');
    }

    public function testCallbackCannotCommitItsManagedTransaction(): void
    {
        try {
            $this->database->transaction(function (Session $database): void {
                $database->execute("INSERT INTO items (name, active, amount) VALUES ('uncommitted', 0, 1)");
                $database->commit();
            });
            $this->fail('A managed callback cannot end its own transaction.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('callback owns', $error->getMessage());
        }
        $this->assertSame(0, $this->countItems());
    }

    public function testNestedTransactionsAreRejectedWithoutClosingTheOuterTransaction(): void
    {
        $this->database->beginTransaction();
        try {
            $this->database->beginTransaction();
            $this->fail('A nested transaction must fail.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('Nested', $error->getMessage());
        }
        $this->assertTrue($this->database->inTransaction());
        $this->database->rollBack();
    }

    public function testMapperFailureLeavesNoOpenCursor(): void
    {
        $this->insert('mapped');
        try {
            $this->database->fetchOne('SELECT * FROM items', fn (Row $row): Item => (new FailingMapper())->map($row));
            $this->fail('The mapper must fail.');
        } catch (RuntimeException $error) {
            $this->assertSame('mapper failure', $error->getMessage());
        }
        $this->database->execute('DROP TABLE items');
    }

    public function testDatabaseFailureHasASafePublicMessageAndTheSessionRecovers(): void
    {
        try {
            $this->database->execute('INSERT INTO private_table VALUES (:secret)', new Parameters(['secret' => 'db-private-canary']));
            $this->fail('Invalid SQL must fail.');
        } catch (DatabaseError $error) {
            $this->assertSame('Database statement failed.', $error->getMessage());
            $this->assertFalse(str_contains($error->getMessage(), 'private'));
        }
        $this->insert('recovery');
        $this->assertSame(1, $this->countItems());
    }

    /** @return list<array{string}> */
    public static function invalidParameters(): array
    {
        return [['empty'], ['invalid'], ['duplicate'], ['gap'], ['mixed'], ['infinity'], ['nan']];
    }

    #[DataProvider('invalidParameters')]
    public function testInvalidBindingsAreRejected(string $scenario): void
    {
        $values = match ($scenario) {
            'empty' => ['' => 1],
            'invalid' => ['bad-name' => 1],
            'duplicate' => ['a' => 1, ':a' => 2],
            'gap' => [1 => 'gap'],
            'mixed' => [0 => 1, 'a' => 2],
            'infinity' => ['x' => INF],
            default => ['x' => NAN],
        };
        $this->expectException(InvalidArgumentException::class);
        new Parameters($values);
    }

    public function testRowMappingIsStrictAndNullableColumnsStayDistinctFromMissingOnes(): void
    {
        $row = new Row(['number' => 7, 'name' => 'Ada', 'yes' => 1, 'no' => 0, 'flag' => true, 'null' => null]);
        $this->assertSame(7, $row->integer('number'));
        $this->assertSame(7.0, $row->float('number'));
        $this->assertTrue($row->boolean('yes'));
        $this->assertTrue($row->boolean('flag'));
        $this->assertFalse($row->boolean('no'));
        $this->assertNull($row->nullableString('null'));
        $this->assertNull($row->nullableInteger('null'));
        $this->expectException(MappingError::class);
        $row->nullableString('missing');
    }

    public function testTextNumbersAreNotSilentlyCoercedIntoIntegerDtoFields(): void
    {
        $this->expectException(MappingError::class);
        (new Row(['number' => '7']))->integer('number');
    }

    private function insert(string $name): void
    {
        $this->database->execute('INSERT INTO items (name, active, amount) VALUES (?, ?, ?)', new Parameters([$name, false, 0]));
    }

    private function countItems(): int
    {
        $count = $this->database->fetchOne('SELECT COUNT(*) AS total FROM items', fn (Row $row): CountResult => (new CountMapper())->map($row));
        if ($count === null) {
            throw new LogicException('COUNT must return one row.');
        }

        return $count->value;
    }

    private static function item(mixed $item): Item
    {
        if (!$item instanceof Item) {
            throw new LogicException('Expected an item DTO.');
        }

        return $item;
    }
}
