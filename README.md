<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="assets/logo_dark_transparent.png">
    <source media="(prefers-color-scheme: light)" srcset="assets/logo_transparent.png">
    <img src="assets/logo_transparent.png" alt="Phex logo" width="420" height="168">
  </picture>
</p>

<h1 align="center">Phex</h1>

<p align="center">
  <em>Run on PHP. Compile with ElephC.</em>
</p>

<p align="center">
  <a href="#requirements"><img src="https://img.shields.io/badge/PHP-8.3%2B-F06445?style=flat-square&amp;logo=php&amp;logoColor=white" alt="PHP 8.3 or newer"></a>
  <a href="https://github.com/illegalstudio/elephc"><img src="https://img.shields.io/badge/ElephC-0.27.6-F06445?style=flat-square&amp;logoColor=white" alt="ElephC 0.27.6"></a>
  <a href="#overview"><img src="https://img.shields.io/badge/status-in%20development-F06445?style=flat-square&amp;logoColor=white" alt="Status: in development"></a>
</p>

<p align="center">
  <strong>PHP-compatible sources &middot; Native compilation &middot; Immutable HTTP &middot; Typed middleware</strong>
</p>

<p align="center">
  A PHP web framework designed first for native compilation with ElephC.
  Run the same shared sources directly on PHP, with immutable HTTP messages,
  typed handlers and middleware, and an explicit middleware pipeline.
</p>

---

## Overview

Phex is a PHP web framework designed for native compilation with ElephC. Shared
application sources run directly on PHP, with explicit dependency injection,
immutable HTTP messages, typed middleware and precompilable routing.

Develop on PHP without generating artifacts. Build generated PHP for PHP-FPM,
or compile a native HTTP executable. Native application lifecycle can retain
application services per worker while each request receives an isolated scope.

Phex is in development. The current checkout includes a portable application
skeleton, typed JSON/query input, named URLs, structured logging and an optional
PDO module with a persistent SQLite CRUD example.

## Documentation

**[Read the Phex handbook](docs/README.md)** for focused guides, practical examples
and a learning path from your first endpoint to PHP/native deployment.

| Start with | Learn how to |
| --- | --- |
| [Installation](docs/installation.md) | Create an application from this checkout |
| [Quickstart](docs/quickstart.md) | Add a typed endpoint, service and named route |
| [Lifecycle](docs/lifecycle.md) | Separate application state from request state |
| [HTTP guides](docs/README.md#http) | Route requests, compose middleware and validate input |
| [SQLite CRUD](docs/database-tutorial.md) | Persist data through PHP and native HTTP |
| [Builds](docs/builds.md) | Choose development, generated PHP or native execution |
| [Native servers](docs/native.md) | Configure workers, application lifetime and startup |

## Requirements

- PHP 8.3 or newer within PHP 8.x and Composer 2 for applications.
- A complete ElephC SDK for native compilation; CI tests release 0.27.6 and a
  source SDK pinned to `9419f7ac` with application-lifecycle support.
- PDO and the selected driver when using the optional database adapter.
- Python 3.10+ and `pdo_sqlite` for framework development checks.

Linux native builds also need the platform's assembler/linker, libc development
files and bzip2 development library. See [Installation](docs/installation.md).

## Create an application

From a framework checkout, before a versioned package release:

```sh
composer install --no-dev
php bin/phex new ../my-app --local
cd ../my-app
composer install
composer serve
```

Open **http://127.0.0.1:8080/** for `{"hello":"world"}`. Development PHP needs no
build artifacts. `--local` adds an explicit mirrored Composer path dependency
on this checkout. Continue with the [Quickstart](docs/quickstart.md).

## Build PHP or native output

Inside the created application:

```sh
composer build
php -S 127.0.0.1:8080 -t public public/compiled.php
```

For native execution, after stopping the PHP server:

```sh
composer build:native
./var/build/start --listen 127.0.0.1:8080 --workers 1
```

[Builds](docs/builds.md) explains regeneration. [PHP deployment](docs/deployment.md)
and [Native servers](docs/native.md) describe runtime configuration and artifacts.

## Examples

The framework checkout includes [hello-world](examples/hello-world/),
[routing](examples/json-api/), [request lifetimes](examples/request-lifecycle/),
[compiled containers](examples/compiled-container/) and
[persistent SQLite CRUD](docs/database-tutorial.md). Full application templates
live in [stubs/application](stubs/application/).

## Static analysis

From the framework repository root:

```sh
composer install
composer tools:install
composer analyse
```

PHPStan runs at maximum level without a baseline or broad suppressions. PHP files
follow the [required purpose-header template](docs/contributing.md#php-file-headers).
The [contribution guide](docs/contributing.md) describes shared-source conventions.

## Test both runtimes

```sh
composer check
```

The framework checks generated artifacts, installed HTTP applications and matching
PHP/ElephC behavior cases. Application-capable SDKs can also run
`composer test:persistent`. See [Testing](docs/testing.md) for requirements and
application test examples, or [Benchmarking](docs/benchmarks.md) for measurement tools.

## License

Phex is licensed under the [MIT License](LICENSE).
Copyright (c) 2026 Vincenzo Petrucci (Illegal Studio).
