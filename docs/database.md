# Database access

[Documentation](README.md)

The optional PDO adapter provides prepared SQL, typed row mapping and request-owned transaction cleanup. SQLite is the driver covered by the shared compatibility and HTTP examples.

## On this page

- [Enable the adapter](#enable-the-adapter)
- [Include optional declarations in static entries](#include-optional-declarations-in-static-entries)
- [Prepare values](#prepare-values)
- [Map rows to DTOs](#map-rows-to-dtos)
- [Transactions](#transactions)
- [Connection ownership](#connection-ownership)

## Enable the adapter

For PHP, install PDO and the required database driver. Native builds need a complete SDK containing the PDO bridge. Runtime applications that do not use this module have no PDO requirement.

Add bindings to your application's service list, importing the shown types:

```php
use Phex\Contracts\Database\Connection;
use Phex\Database\ConnectionConfig;
use Phex\Database\PDOConnection;
use Phex\Database\Session;

Service::factory(ConnectionConfig::class, Lifetime::Application, DatabaseFactory::class, 'configuration'),
Service::factory(Connection::class, Lifetime::Application, PDOConnection::class, 'connect'),
Service::constructor(Session::class, Lifetime::Request),
```

`DatabaseFactory` is your application factory. Its public static `configuration(Environment $environment): ConnectionConfig` can return:

```php
return new ConnectionConfig($environment->nonEmptyString('PHEX_DATABASE_DSN'));
```

The existing environment bindings remain in place. Inject `Session` into request-scoped repositories. The [complete example](../examples/database/config/services.php) demonstrates all bindings.

## Include optional declarations in static entries

Development PHP uses Composer autoloading. Generated PHP and native execution use explicit declaration graphs. In your application's `public/compiled.php`, `native/main.php` and `native/application.php`, add this before the generated bootstrap include:

```php
require_once __DIR__.'/../vendor/getphex/framework/src/Database/bootstrap.php';
```

This opts the entry into the database module. The core shared bootstrap does not load database declarations for applications that do not use them. Rebuild after changing these entries.

## Prepare values

```php
use Phex\Database\Parameters;

$affected = $database->execute(
    'INSERT INTO users (name) VALUES (:name)',
    new Parameters(['name' => $name]),
);
$id = $database->lastInsertId();
```

`execute` returns an affected-row count. `lastInsertId` returns the physical driver's last identity as a string; use it immediately after the insert whose identity you need.

Bindings can be a zero-based positional list or a named map. Named keys can include their leading colon. Mixed/gapped keys, invalid names and duplicate normalized names are rejected. Values are null, booleans, integers, strings or finite floats. Floats bind as strings because PDO has no float parameter type; this is not an exact-decimal arithmetic API.

Placeholders bind values, not table names or SQL fragments. Keep SQL structure explicit in repository code. In an HTTP endpoint, alias database `Parameters` if you also import route `Parameters`.

## Map rows to DTOs

For an application `User` DTO with an integer ID and string name:

```php
use Phex\Database\Row;

$user = $database->fetchOne(
    'SELECT id, name FROM users WHERE id = :id',
    fn (Row $row): User => new User($row->integer('id'), $row->string('name')),
    new Parameters(['id' => $id]),
);
```

The generic result is `?User`; no matching row returns null. `fetchAll` takes the same arguments and returns a list of mapped objects, or an empty list.

A reusable mapper can implement `Phex\Contracts\Database\RowMapper<User>`. Pass it through a typed closure:

```php
$users = $database->fetchAll(
    'SELECT id, name FROM users ORDER BY id',
    fn (Row $row): User => $mapper->map($row),
);
```

Use explicit closure parameter and result types for portable native inference. The example's [User](../examples/database/app/User.php) and [UserMapper](../examples/database/app/UserMapper.php) show complete types.

`Row` performs strict conversion. Strings and integers are not implicitly interchanged. Booleans accept bool or integer `0`/`1`; `float` accepts integers and floats. Nullable readers distinguish SQL null from a missing column, which raises `MappingError`. Other PDO drivers may produce different scalar types and need explicit mapping policy and their own compatibility coverage.

## Transactions

A managed callback commits on success and rolls back on failure:

```php
$database->transaction(function (Session $session): void {
    $session->execute(
        'INSERT INTO users (name) VALUES (:name)',
        new Parameters(['name' => 'Ada']),
    );
});
```

The callback returns void. It owns its boundary, so calling `commit`, `rollBack`, `close` or starting a nested transaction inside it is rejected. Explicit `beginTransaction`, `commit` and `rollBack` are available when your method needs its own result flow. See the example [repository](../examples/database/app/UserRepository.php).

Closing a request session rolls back unfinished work and releases the connection lease. Failed rollback closes the physical driver so it cannot be silently reused. Database-specific implicit commits, including some DDL behavior, are outside a portable transaction guarantee.

## Connection ownership

With native application lifecycle, each worker retains an application-owned `PDOConnection` and receives a fresh `Session` for each request. PHP front controllers and native request lifecycle close their application connections per request. No PDO persistent-connection option is enabled.

One physical connection accepts one active session. A second simultaneous lease fails rather than sharing transaction state. There is no automatic connection pool or concurrent-session multiplexing.

Results are buffered and statement cursors close before application mapping. There is no streaming cursor, query builder, ORM or migration runner in this module. Start with the [SQLite CRUD tutorial](database-tutorial.md) for a complete working path.

---

[Previous: Structured logging](logging.md) | [Next: Run the SQLite CRUD example](database-tutorial.md)
