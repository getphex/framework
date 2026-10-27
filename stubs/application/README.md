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

Send typed query input:

```sh
curl 'http://127.0.0.1:8080/search?page=2&limit=10&active=true&sort=desc'
```

The example returns `{"page":2,"limit":10,"active":true,"sort":"desc"}`.
`SearchDecoder` maps query parameters to `SearchInput`, validates page/limit
ranges and the sort order, and rejects unknown parameters. Missing options use
defaults. Invalid or repeated scalar values return 422; malformed escapes return
400. `Handlers` applies `InputErrorMiddleware` to both query and JSON input.

Routes are named `home`, `greet` and `search`. Inject
`Phex\Contracts\Routing\UrlGenerator` to generate paths from those definitions;
`HomeAction` demonstrates this with a `Link` header for the search route.
Route names are unique across HTTP methods. Path parameters are explicit string
or integer values and are encoded as complete segments. PHP development picks
up route edits on the next request; compiled targets use the generated registry.

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

Application settings live in the immutable `app/Config/ApplicationSettings.php`.
`SettingsFactory` reads them at application-container startup through the typed
environment service. The defaults are `PHEX_GREETING=world`, `PHEX_PAGE_SIZE=20`
and `PHEX_UPPERCASE_GREETING=false`.

```sh
PHEX_GREETING=phex PHEX_PAGE_SIZE=10 PHEX_UPPERCASE_GREETING=true composer serve
```

This returns `{"hello":"PHEX"}` from `/` and uses ten as the default page size
for `/search`. Supply the same variables when starting the generated PHP host
or native executable. Restart the host to change its process environment;
the native binary does not need rebuilding. Required variables and invalid
values raise `InvalidConfiguration` at container construction. The host reports
unexpected failures with its generic `500` response.

The factory is registered explicitly at application lifetime in
`config/services.php`; build tools inspect its signature and do not read its
environment values. Keep credentials in runtime variables. Source defaults are
compiled into the executable. PHP-FPM pools must explicitly provide the process
variables needed by the application.

PHP source edits are visible on subsequent development requests, subject to
OPcache timestamp settings. Rebuild compiled PHP artifacts after structural
changes, and recompile the native executable after any source change.
Persistent PHP workers are a future adapter.
