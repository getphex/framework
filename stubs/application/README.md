# Phex application

The official [getphex/phex repository](https://github.com/getphex/phex) is an
automatically published mirror of
[`stubs/application` in getphex/framework](https://github.com/getphex/framework/tree/main/stubs/application).
Contribute template changes in the framework monorepo. Projects created from this
template are independent applications.

This project runs directly on PHP and can be compiled with ElephC.
Read the [Phex handbook](https://github.com/getphex/framework/blob/main/docs/README.md)
for complete guides.

## Develop on PHP

From this application directory:

```sh
composer install
composer serve
```

Open http://127.0.0.1:8080/ for `{"hello":"world"}`. Edit application types in
`app/`, bindings in `config/services.php` and routes in `routes/web.php`.
Development PHP needs no generated artifacts.

```sh
curl -i http://127.0.0.1:8080/greet \
  -H 'Content-Type: application/json' --data '{"name":"Ada"}'
curl 'http://127.0.0.1:8080/search?page=2&limit=10&active=true&sort=desc'
```

The [Quickstart](https://github.com/getphex/framework/blob/main/docs/quickstart.md)
walks through a new endpoint and service.

## Build generated PHP

```sh
composer build
php -S 127.0.0.1:8080 -t public public/compiled.php
```

Rebuild after structural changes to routes, bindings or source declarations.
The [build guide](https://github.com/getphex/framework/blob/main/docs/builds.md)
contains the complete regeneration matrix.

## Compile native HTTP

```sh
composer build:native
./var/build/start --listen 127.0.0.1:8080 --workers 1
```

Stop an existing server before reusing its port. `config/server.php` chooses
Request lifecycle by default. Application lifecycle retains one container per
worker and needs an SDK with `--web-lifecycle` support. Select a compiler through
`PHEX_ELEPHC`.

The [native server guide](https://github.com/getphex/framework/blob/main/docs/native.md)
explains lifecycle, startup overrides, artifacts and tested runtime limitations.

## Configure and test

Settings are read at application startup through typed environment factories:

```sh
PHEX_GREETING=phex PHEX_PAGE_SIZE=10 PHEX_UPPERCASE_GREETING=true composer serve
```

Supply credentials through runtime configuration. Each request receives a fresh
trusted `X-Request-Id`, used in structured server logs. Keep request dependencies
at request lifetime when using retained native workers.

See [Configuration](https://github.com/getphex/framework/blob/main/docs/configuration.md),
[Logging](https://github.com/getphex/framework/blob/main/docs/logging.md) and
[Testing](https://github.com/getphex/framework/blob/main/docs/testing.md) for full examples.
