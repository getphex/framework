# Persistent SQLite CRUD example

This example uses the optional `Phex\Database` PDO adapter, prepared parameters,
explicit DTO mapping, compiled routing and request-owned database sessions.
Run all commands from the repository root. PHP needs `pdo_sqlite`; native builds
need an ElephC SDK containing the PDO bridge. SQLite is the tested driver.

## Run with PHP

```sh
composer install
mkdir -p examples/database/var
export PHEX_DATABASE_DSN="sqlite:$PWD/examples/database/var/users.sqlite"
php examples/database/init.php
php -S 127.0.0.1:8080 -t examples/database/public examples/database/public/index.php
```

Development PHP needs no generated artifacts. Schema initialization is an
explicit command, independent of application builds and HTTP requests. The DSN
is read at runtime and is not written into generated code.

For generated PHP, first run:

```sh
php bin/phex build examples/database/config/build.php
php -S 127.0.0.1:8080 -t examples/database/public examples/database/public/compiled.php
```

Use the same exported DSN for initialization and serving. Use the compiled front
controller with PHP-FPM when deploying generated PHP.

## Compile with ElephC

```sh
php bin/phex compile examples/database/config/build.php
examples/database/var/build/server --listen 127.0.0.1:8080 --workers 1
```

Set `PHEX_ELEPHC` to select an SDK. The default server configuration uses request
lifecycle. To retain the application and physical connection within each native
worker, edit `config/server.php`:

```php
return new \Phex\Build\Config\NativeServerConfig(
    lifecycle: \Phex\Build\Config\NativeLifecycle::Application,
    workers: 1,
);
```

Recompile after changing server configuration. Application lifecycle requires
ElephC with `--web-lifecycle application` support; CI also tests a pinned source
SDK. Each worker owns its own physical connection. SQLite remains subject to
its write-locking behavior; increasing workers does not make writes parallel.

## Exercise the API

```sh
curl -i http://127.0.0.1:8080/users \
  -H 'Content-Type: application/json' --data '{"name":"Ada"}'
curl http://127.0.0.1:8080/users
curl http://127.0.0.1:8080/users/1
curl -X PUT http://127.0.0.1:8080/users/1 \
  -H 'Content-Type: application/json' --data '{"name":"Grace"}'
curl -i -X DELETE http://127.0.0.1:8080/users/1
```

Creation returns `201`, a typed `{id, name}` JSON object and a named `Location`
header. Missing users return `404`, invalid names return `422`, and successful
deletion returns `204`. File-backed rows persist across requests in every target.

## Ownership and mapping

`config/services.php` binds `Connection` to `PdoConnection` with application
lifetime and `Session` with request lifetime. Repositories receive the session,
never the physical PDO handle. A physical connection permits one active session.
Closing the request session rolls back an unfinished transaction and releases
its lease. Application cleanup closes the physical connection. No PDO persistent
connection option is enabled.

The repository uses prepared statements and explicit transaction boundaries.
User input is passed through `Parameters`, never concatenated into SQL.
`UserMapper` implements `RowMapper<User>`, and typed closure literals preserve the
DTO type through generic `Session::fetchOne` and `fetchAll` calls.

`Parameters` accepts named scalar/null bindings or a zero-based positional list.
Names may include a leading colon. Nulls, booleans, integers and strings use their
PDO parameter types. Finite floats bind as strings because PDO has no float
parameter type. This does not provide an exact decimal arithmetic API. SQL
identifiers and arbitrary SQL fragments cannot be bound as values.

`Row` validates driver results explicitly: integer and string accessors do not
coerce types; booleans accept booleans or integer `0`/`1`; floating access accepts
integers and floats. Missing columns raise `MappingError`, independently of SQL
null. Other PDO drivers may return different scalar types and require their own
mapping policy and compatibility tests.

`Session::transaction(callable(Session): void)` commits on success and rolls back
on failure. Its callback cannot end the managed transaction or nest another one.
Explicit `beginTransaction`, `commit` and `rollBack` are also available. Do not
rely on rollback for DDL that a database implicitly commits. Results are buffered;
there is no streaming cursor, query builder, migration framework or ORM.

The optional declaration graph is `src/Database/bootstrap.php`; native entries
include it explicitly before their generated bootstrap. Database-free entries
do not include that graph. PDO objects stay inside the physical adapter.

## Verify

```sh
composer test:database
python3 -B tools/checks/database_smoke.py --php-only
```

The integration check installs the example in a separate Composer project and
exercises development PHP, generated PHP and native HTTP. It verifies CRUD,
prepared values, typed output, connection lifetime, unfinished-transaction
rollback and recovery after a failed request. Application lifecycle is also
checked when supported by the selected compiler, including two workers with
bounded recycling and rows shared through the SQLite file.
