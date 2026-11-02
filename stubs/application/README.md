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

Every application response carries a server-generated `X-Request-Id`, including error
responses. Client-supplied IDs are replaced. The same ID appears in structured
JSON logs written through the host's `error_log` destination. The outer
`RequestLoggingMiddleware` logs completed responses; unhandled exceptions are
reported once by the host, with the correlation ID and a generic 500 response.

Inject `Phex\Contracts\Logging\Logger` into request services and endpoints:

```php
use Phex\Logging\Level;
use Phex\Logging\Record;

$logger->log(new Record(Level::Info, 'greeting.sent', ['recipient_id' => 7]));
```

Context fields are named scalar values or null. The logger adds UTC time and
level; the request wrapper adds ID and method. These reserved fields cannot be
overwritten. Query strings, headers, bodies and traces are not logged
automatically. Messages and explicit fields are the application's responsibility.
Application services use `JsonLogger` for events without request context; the
container rejects application services that capture the request `Logger`.
The logging API and JSON format are shared by development PHP, compiled PHP and
ElephC. Direct kernel calls using this service configuration must supply a
validated `X-Request-Id`, which the normal web entry points generate themselves.

Build the generated PHP container, matcher and static bootstraps:

```sh
composer build
php -S 127.0.0.1:8080 -t public public/compiled.php
```

Compile and start the native server:

```sh
composer build:native
./var/build/start
```

`config/server.php` selects the native lifecycle and startup defaults:

```php
return new NativeServerConfig(
    lifecycle: NativeLifecycle::Request,
    listen: '127.0.0.1:8080',
    workers: 4,
    maxRequests: 1000,
);
```

`Request` is the default and works with ElephC 0.27.6. Change it to `Application`
to initialize one application container per worker after fork. That mode requires
a compiler with `--web-lifecycle` support, such as `nightly-20261029`:

```sh
PHEX_ELEPHC="$(elvm which nightly-20261029)" composer build:native
./var/build/start
```

`PHEX_ELEPHC` accepts a compiler executable path. Persistent application state is
private to each worker; every request still has a new request scope, logger
context and ID. Request failures release request resources and allow subsequent
requests to use the same application. Application mutations made before a
failure remain visible. Application resources close during graceful shutdown or
worker recycling. Keep request-specific dependencies at request lifetime.
Invalid application settings fail persistent bootstrap before the worker accepts
traffic. That startup failure has no HTTP request or request ID.

Workers, listen address and recycling are startup settings. Override them without
compiling:

```sh
PHEX_WORKERS=8 PHEX_LISTEN=0.0.0.0:8080 ./var/build/start
./var/build/start --workers 2 --max-requests 0 -- --access-log
```

Launcher options override `PHEX_LISTEN`, `PHEX_WORKERS` and `PHEX_MAX_REQUESTS`,
which override the file defaults. Pass extra native options after `--`.
`maxRequests: 0` disables recycling. To change defaults in the file, run
`composer build` and restart; this regenerates the launcher without recompiling
the executable. Changing lifecycle requires `composer build:native`, and the
launcher refuses a lifecycle that does not match `server.lifecycle` from the last
successful compilation.

Deploy `var/build/server`, `var/build/server.lifecycle` and `var/build/start`
together. The launcher uses a POSIX shell and `dirname`; PHP and Composer are not
needed to start the native application. `composer serve:native` is an optional
local convenience command.

For application static analysis, scan the compiler API declarations in
`vendor/getphex/phex/src/Runtime/Elephc/analysis.stub`. They are not runtime
polyfills and must not be required by application entry points.

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
