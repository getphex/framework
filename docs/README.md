# Phex documentation

Build a typed web application on PHP, then compile the same shared sources with ElephC. Phex provides explicit dependency injection, immutable buffered HTTP messages, precompilable routing and separate application/request ownership.

This handbook describes the current development checkout. Start on PHP with no generated artifacts, then choose generated PHP or native execution when you need a build. Package creation currently uses the explicit local-checkout installation flow.

## Start here

1. [Install Phex and create an application](installation.md).
2. [Build your first endpoint](quickstart.md), including a service and named route.
3. [Understand lifetimes](lifecycle.md) before retaining application objects in native workers.
4. [Run persistent SQLite CRUD](database-tutorial.md) when the application needs storage.

## Find a topic

### Getting started

- [Installation](installation.md)
- [Your first endpoint](quickstart.md)
- [Directory structure](structure.md)

### Architecture and services

- [Request and application lifecycle](lifecycle.md)
- [The service container](container.md)
- [Configuration](configuration.md)
- [Structured logging](logging.md)

### HTTP

- [Routing](routing.md)
- [Middleware](middleware.md)
- [Requests and responses](requests-responses.md)
- [JSON input and output](json.md)
- [Query strings](query-strings.md)
- [URL generation](urls.md)

### Database

- [Database access](database.md)
- [Run the SQLite CRUD example](database-tutorial.md)

### Build and deployment

- [Builds and development](builds.md)
- [Deploy on PHP](deployment.md)
- [Native servers](native.md)

### Testing and reference

- [Testing](testing.md)
- [CLI reference](cli.md)
- [Troubleshooting](troubleshooting.md)
- [Contributing to Phex](contributing.md)
- [Benchmarking and profiling](benchmarks.md)

## Read examples in context

Application guides use the project created by `phex new`. A page that runs the repository's `examples/` or `composer check` says **framework checkout** explicitly. Code fragments belong in the file or method named beside them; the quickstart provides complete files.

The current web API is buffered. The database module uses explicit SQL/DTO mapping and has SQLite compatibility coverage. Streaming, an ORM/migration system and a persistent PHP host adapter are outside the current documented feature set.

The [framework examples](../examples/) and [application skeleton](../stubs/application/) provide complete source alongside the guides. For problems, start with [Troubleshooting](troubleshooting.md).
