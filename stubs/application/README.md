# Phex application

Install dependencies and start PHP development:

```sh
composer install
composer serve
```

Open http://127.0.0.1:8080/ for `{"hello":"world"}`. No generated artifacts are
required. Application classes live in `app/`, explicit service definitions in
`config/services.php`, and routes in `routes/web.php`.

Send typed JSON input:

```sh
curl -i http://127.0.0.1:8080/greet \
  -H 'Content-Type: application/json' --data '{"name":"Ada"}'
```

The result is `{"hello":"Ada"}`. Invalid JSON returns 400, an unsupported content
type returns 415, and invalid field values return 422.

Build the generated PHP container, matcher and static bootstraps:

```sh
composer build
php -S 127.0.0.1:8080 -t public public/compiled.php
```

Compile the native server with ElephC 0.27.6:

```sh
composer build:native
./var/build/server --listen 127.0.0.1:8080 --workers 1
```

The native runtime uses the generated declarations and native adapter. PHP
development uses Composer and the interpreted container. Shared application
sources remain ordinary PHP. Keep PHP development code under `bootstrap/` or
`public/`; `app/` contains the shared PSR-4 types loaded by compiled builds.
Use one autoloadable type per source file. Additional shared source namespaces
can be configured in `config/build.php` through `ApplicationConfig::sources`.

PHP source edits are visible on subsequent development requests, subject to
OPcache timestamp settings. Rebuild compiled PHP artifacts after structural
changes, and recompile the native executable after any source change.
Persistent PHP workers are a future adapter.
