# Installation

[Documentation](README.md)

Phex applications run directly on PHP and can be compiled into a native HTTP server with ElephC. Start with PHP development; add native compilation when your application is ready.

## On this page

- [Requirements](#requirements)
- [Create an application from this checkout](#create-an-application-from-this-checkout)
- [Run the included endpoints](#run-the-included-endpoints)
- [Generated PHP and native builds](#generated-php-and-native-builds)
- [Next steps](#next-steps)

## Requirements

| Task | Requirements |
| --- | --- |
| Run an application on PHP | PHP 8.3 or newer within PHP 8.x, Composer 2 |
| Compile a native server | A complete ElephC SDK, assembler, linker and platform development libraries |
| Use the database adapter on PHP | PDO and the driver for your database; examples use `pdo_sqlite` |
| Work on the framework | Development dependencies, `pdo_sqlite`, Python 3.10+, and ElephC for compatibility checks |

On Debian or Ubuntu, native builds need `libbz2-dev` in addition to the usual native build tools. ElephC 0.27.6 supports the default request lifecycle. Retained application lifecycle requires a compiler with `--web-lifecycle` support; see [Native servers](native.md#compiler-requirements).

## Create an application from this checkout

Phex is in development and does not yet have a package release. The framework
package is `getphex/framework`; the `getphex/phex` application repository is an
automatic split of its template. See [Package distribution](distribution.md).

The supported starting point is a framework checkout with an explicit local
Composer dependency:

```sh
# Start outside the framework repository.
git clone https://github.com/getphex/framework.git
cd framework
composer install --no-dev
php bin/phex new ../my-app --local
cd ../my-app
composer install
composer serve
```

Open **http://127.0.0.1:8080/**. The response is:

```json
{"hello":"world"}
```

`--local` writes a path repository for this framework checkout into the new application's Composer manifest. Composer mirrors the framework into the application's `vendor/` directory. Keep the checkout available when updating that dependency. The installed application runs against its own mirrored package.

`phex new` refuses an existing destination. Choose a new directory rather than running it over an application you already use.

## Run the included endpoints

From another terminal:

```sh
curl -i http://127.0.0.1:8080/greet \
  -H 'Content-Type: application/json' --data '{"name":"Ada"}'
curl 'http://127.0.0.1:8080/search?page=2&limit=10&active=true&sort=desc'
```

The greeting returns `{"hello":"Ada"}`. The search returns validated options:

```json
{"page":2,"limit":10,"active":true,"sort":"desc"}
```

The PHP development server loads service and route definitions on each request. No `build` command is needed to start developing. With OPcache enabled, use timestamp validation and a development-appropriate revalidation interval so edits become visible.

## Generated PHP and native builds

Inside **your application**, generated PHP is available explicitly:

```sh
composer build
php -S 127.0.0.1:8080 -t public public/compiled.php
```

For native execution, with ElephC available:

```sh
composer build:native
./var/build/start --listen 127.0.0.1:8080 --workers 1
```

Stop the development server before starting another process on the same address. Both commands serve the application's existing endpoints. [Builds](builds.md) explains when artifacts must be regenerated.

## Next steps

Build a new endpoint in the [Quickstart](quickstart.md), then review the [application structure](structure.md). Framework contributors should use the development installation in [Contributing](contributing.md#development-setup).

---

[Next: Your first endpoint](quickstart.md)
