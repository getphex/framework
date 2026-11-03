# Run the SQLite CRUD example

[Documentation](README.md)

This tutorial uses the complete application in `examples/database/`. It exercises typed JSON input, named routes, prepared statements and request-owned transactions against a real SQLite file.

Run every command on this page from the **framework repository root**. It is an example application in this checkout, rather than a `phex new --database` command.

## On this page

- [Prepare the example](#prepare-the-example)
- [Start development PHP](#start-development-php)
- [Read, update and delete](#read-update-and-delete)
- [Try generated PHP](#try-generated-php)
- [Compile a native server](#compile-a-native-server)
- [Follow the code](#follow-the-code)

## Prepare the example

Install framework development dependencies if necessary, including `pdo_sqlite`:

```sh
composer install
mkdir -p examples/database/var
export PHEX_DATABASE_DSN="sqlite:$PWD/examples/database/var/users.sqlite"
php examples/database/init.php
```

`init.php` creates the `users` table if needed. Schema setup is an explicit operation; the build does not connect to the database or initialize tables. Keep the DSN available in the terminal that starts the application.

## Start development PHP

```sh
php -S 127.0.0.1:8080 -t examples/database/public examples/database/public/index.php
```

From another terminal:

```sh
curl -i http://127.0.0.1:8080/users \
  -H 'Content-Type: application/json' --data '{"name":"Ada"}'
```

On a new database, the response is `201`, includes `Location: /users/1`, and has this body:

```json
{"id":1,"name":"Ada"}
```

IDs depend on existing data. Use the returned location for subsequent requests.

## Read, update and delete

```sh
curl http://127.0.0.1:8080/users
curl http://127.0.0.1:8080/users/1
curl -X PUT http://127.0.0.1:8080/users/1 \
  -H 'Content-Type: application/json' --data '{"name":"Grace"}'
curl -i -X DELETE http://127.0.0.1:8080/users/1
```

| Operation | Result |
| --- | --- |
| List users | `200`, JSON array |
| Read or update an existing user | `200`, user object |
| Delete an existing user | `204`, empty body |
| Read/update/delete a missing user | `404` |
| Submit an invalid name | `422`, validation errors |

Rows survive subsequent requests in every target because the data is file-backed. This remains true when an application container is recreated for each request.

## Try generated PHP

Stop the development server, then run in the terminal containing the exported DSN:

```sh
php bin/phex build examples/database/config/build.php
php -S 127.0.0.1:8080 -t examples/database/public examples/database/public/compiled.php
```

Use the same curl commands. The repository, SQL, mapper and endpoint code are unchanged.

## Compile a native server

With a complete ElephC SDK available, stop PHP and run:

```sh
php bin/phex compile examples/database/config/build.php
examples/database/var/build/start --listen 127.0.0.1:8080 --workers 1
```

The runtime DSN still selects the SQLite file. To select another compiler, set `PHEX_ELEPHC` for the compile command. [Native servers](native.md) describes application-lifecycle capability and deployment artifacts.

For retained application lifetime, set `NativeLifecycle::Application` in `examples/database/config/server.php`, then compile again. Each worker retains its own physical connection while each request gets a new session. SQLite write locking still applies; more workers do not make its writes parallel.

## Follow the code

| File | Responsibility |
| --- | --- |
| [services.php](../examples/database/config/services.php) | Physical connection and request-session bindings |
| [UserInput.php](../examples/database/app/UserInput.php) | JSON name validation |
| [UserRepository.php](../examples/database/app/UserRepository.php) | Prepared CRUD queries and transaction boundaries |
| [UserMapper.php](../examples/database/app/UserMapper.php) | Strict row-to-DTO mapping |
| [UsersEndpoint.php](../examples/database/app/UsersEndpoint.php) | List/create responses and named location |
| [UserEndpoint.php](../examples/database/app/UserEndpoint.php) | Single-user reads, updates and deletion |

Mutations begin and commit transactions explicitly. An exception leaves request cleanup responsible for unfinished work. Request rollback is distinct from reverting arbitrary application memory, as explained in [Lifecycle](lifecycle.md#handle-failures).

For your own application, follow [Database access](database.md), register request-scoped repositories and add the optional declaration bootstrap to static entries. The example initialization script is schema setup, not a migration framework.

---

[Previous: Database access](database.md) | [Next: Builds and development](builds.md)
