# Troubleshooting

[Documentation](README.md)

Start with the command's working directory and the selected runtime. PHP development, generated PHP and a native executable load code through different entry points.

## On this page

- [Composer dependencies are missing](#composer-dependencies-are-missing)
- [A generated bootstrap cannot be found](#a-generated-bootstrap-cannot-be-found)
- [PHP edits do not appear](#php-edits-do-not-appear)
- [Container build failures](#container-build-failures)
- [Native application lifecycle is unsupported](#native-application-lifecycle-is-unsupported)
- [Native linking fails](#native-linking-fails)
- [A JSON endpoint returns 415 or 422](#a-json-endpoint-returns-415-or-422)
- [Database errors or lost rows](#database-errors-or-lost-rows)
- [Worker state is inconsistent](#worker-state-is-inconsistent)
- [Find the server error](#find-the-server-error)

## Composer dependencies are missing

If the CLI asks you to install dependencies, run `composer install` in the project containing that CLI. A framework checkout and a generated application each have their own manifest and `vendor/` directory.

The installation guide uses an explicit `--local` dependency while Phex is unreleased. Keep that source checkout available when Composer needs to update the mirrored package.

## A generated bootstrap cannot be found

Use `public/index.php` for build-free PHP development. For `public/compiled.php`, run `composer build` in your application. For the native launcher, run `composer build:native` first.

Keep generated relative directory relationships intact during deployment. Rebuild after new types, route/binding changes or provider signature changes. See the [rebuild matrix](builds.md#know-when-to-rebuild).

## PHP edits do not appear

Check OPcache timestamp settings and whether you are serving the development or compiled entry. Existing class method bodies can be reloaded by PHP, but new static declaration dependencies require regenerated bootstraps on the compiled path.

A native program contains compiled code. Source edits require recompilation and a restarted process.

## Container build failures

Check that every constructor dependency has an explicit binding or is an available reserved type. Check the lifetime graph for an application service capturing request state, including through transient dependencies.

Factories must be public/static and return an appropriate non-null service type. Unsupported required scalars, union/variadic/by-reference dependencies and cycles are rejected. Validate the provider signature before changing emitted PHP.

## Native application lifecycle is unsupported

Run `elephc --help` for the executable actually selected by the build. It must advertise `--web-lifecycle`. Set `PHEX_ELEPHC` to a complete capable SDK and rebuild after changing lifecycle.

If the launcher reports a lifecycle mismatch, generating configuration alone was insufficient. Compile a new binary and matching success marker.

## Native linking fails

Check platform build dependencies and the complete SDK runtime archives, including the PDO archive for database applications. Linux bzip2 link errors usually require the platform's bzip2 development package. `elephc --print-capabilities` lists the optional bridge archives the compiler expects.

Do not substitute an isolated compiler executable for its full SDK. Build and deploy for the target platform/architecture.

## A JSON endpoint returns 415 or 422

`415` usually means missing, repeated or unsupported `Content-Type`; use `application/json` for the documented curl examples. `422` means parsing succeeded but the decoder rejected the root shape or fields. Send an object rather than a JSON list to `JsonObject`-based decoders.

Use the `errors` response map and [JSON input rules](json.md#input-errors). Unexpected application errors have a generic `500`, not a validation response.

## Database errors or lost rows

Check `PDO::getAvailableDrivers()` in the PHP runtime serving the request, and verify the native SDK's PDO bridge separately. A file-backed SQLite DSN must point at an initialized database and writable directory. `sqlite::memory:` is local to its physical connection and cannot persist across recreated connections.

Use the same runtime DSN for schema initialization and the server. The build never initializes the database. Unfinished or failed request transactions are intentionally rolled back; explicit committed writes are covered by the [CRUD tutorial](database-tutorial.md).

## Worker state is inconsistent

Native application workers have independent containers. An in-memory counter is not a shared database. Put request-specific values at request lifetime and durable shared state in external storage.

The pinned runtime has a known connection continuity limitation around quota recycling; see [Native servers](native.md#quota-recycling-in-the-pinned-runtime). Distinguish that transport event from an HTTP error response or SQL failure.

## Find the server error

Use the response's `X-Request-Id` to correlate [structured logs](logging.md). Check environment configuration, input validation and resource cleanup separately. Avoid exposing exception details to HTTP clients just to make diagnostics easier.

---

[Previous: CLI reference](cli.md) | [Next: Contributing to Phex](contributing.md)
