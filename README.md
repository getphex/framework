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

Phex is in its initial implementation increments. The HTTP core provides immutable
requests, responses, and headers, typed handler and middleware contracts, and an
explicit middleware pipeline. Shared sources run directly on PHP and compile with
ElephC. Generic PHPDoc annotations specialize the pipeline assembly method while
the runtime pipeline exposes the shared handler contract. Routing adds immutable
definitions, a replaceable matcher, and explicit path parameter conversion. The
kernel creates an isolated scope for every request and releases its resources
before returning the response. The build tool validates explicit dependency graphs
and emits typed PHP containers for both runtimes. PHP development can interpret
the same definitions without generating artifacts.
An independent application skeleton and Composer-installed CLI support creating
projects, generating static bootstraps and compiling native web servers.

## Requirements

- PHP 8.3 or newer within PHP 8.x.
- Composer 2.
- ElephC 0.27.6 for native compilation and compatibility tests.
- Python 3.10 or newer for the compatibility checker.

On Linux, native builds also need an assembler, linker, libc development files,
and the bzip2 development library (`libbz2-dev` on Debian and Ubuntu).

## Create an application

Develop the framework and application skeleton together in this repository.
The template lives under `stubs/application/` and is an independent Composer
project using the `App\` namespace and requiring `getphex/phex`.

To try the current checkout before a package release:

```sh
composer install
php bin/phex new ../my-app --local
cd ../my-app
composer install
composer serve
```

Open http://127.0.0.1:8080/ for `{"hello":"world"}`. Development PHP needs no
build artifacts. Test the typed JSON action:

```sh
curl -i http://127.0.0.1:8080/greet \
  -H 'Content-Type: application/json' --data '{"name":"Ada"}'
```

The result is `{"hello":"Ada"}`. Application classes live in `app/`, service
definitions in `config/services.php`, and routes in `routes/web.php`.
The PHP development entry loads Composer through `bootstrap/app.php`.

`new --local` adds a Composer path repository for the current package, with a
local version alias and mirroring enabled. Installed code resides in the new
project's `vendor/` directory. The default `new <directory>` template contains
only a normal package dependency and no checkout paths. It requires an available
package release or a repository configured by the application owner. Existing
destinations are rejected, and generated files and installed dependencies are
excluded from template copies. Composer exposes the installed CLI as
`vendor/bin/phex` using its binary proxy and runtime API.

For generated PHP and native execution, from the application directory:

```sh
composer build
php -S 127.0.0.1:8080 -t public public/compiled.php
```

```sh
composer build:native
./var/build/server --listen 127.0.0.1:8080 --workers 1
```

`config/build.php` returns `ApplicationConfig`. Building emits an application
container/matcher plus separate PHP and native bootstraps under `var/generated/`.
The build host discovers shared PSR-4 types under the configured source roots
(`App\` in `app/` by default) and orders declaration dependencies, including
parents, interfaces, traits and override signature types. Use one autoloadable
type per file. Generated includes use relative paths; the installed project can
be moved without rebuilding its PHP bootstraps.

The runtime bootstraps load fixed framework declarations, application types and
generated classes. Native execution loads the native adapter. PHP development
uses a separate entry and interpreted container. Keep development-only files
outside the shared `app/` source roots. `Runtime/Http/WebApplication` hosts all
targets through typed reader/output boundaries and the shared
`ApplicationContainer` contract, with request and application cleanup.
An explicit `ErrorReporter` handles unexpected failures at the runtime boundary;
PHP uses SAPI logging, and the native web reporter logs the exception message.

Build-time validation finishes before artifact replacement. Files are written
atomically and identical output is retained. `compile` invokes ElephC with an
argument vector, removes assembly intermediates, and removes a stale executable
if compilation fails. Adding types or changing routes, providers or declaration
dependencies requires rebuilding PHP artifacts; native source changes require
recompilation.

The skeleton stays in this monorepo and can later be extracted into a separate
project package. The generated application accesses the framework through its
installed dependency. Persistent PHP workers remain a later host adapter.

## Try the example

```sh
composer install
composer tools:install
php examples/hello-world/main.php
```

The output is `{"hello":"world"}`. The example uses explicit declaration includes,
so its bootstrap runs unchanged with PHP and ElephC. To compile it, create a small
entry point in the ignored `var/` directory:

```sh
mkdir -p var/build
printf '%s\n' '<?php require __DIR__."/../../examples/hello-world/main.php";' > var/build/hello.php
elephc --strict-php var/build/hello.php
./var/build/hello
```

## Compose middleware

```php
use Phex\Examples\HelloWorld\GreetingHandler;
use Phex\Examples\HelloWorld\JsonMiddleware;
use Phex\Http\Message\Request;
use Phex\Http\Middleware\Pipeline;

$application = Pipeline::wrap(new JsonMiddleware(), new GreetingHandler());
$response = $application->handle(new Request('GET', '/hello'));
```

Each pipeline node wraps one middleware and its next handler. Nest nodes to add
middleware: the outer node runs first on the request and last on the response.
Middleware may return early or call its next handler more than once. The pipeline
keeps no current request or traversal cursor. Handler and middleware instances are
reused; their implementations must keep request data in local variables.

## HTTP messages

`Request` and `Response` expose readonly properties and return new instances from
their `with*` methods. Bodies are currently in-memory strings and preserve binary
bytes. PHP SAPI and ElephC's native web bridge support buffered HTTP responses;
streaming and persistent PHP worker adapters are subsequent increments.

`Headers` accepts a map of names to nonempty lists of string values. Names are
case-insensitive and exported in lowercase. Repeated values preserve their order
and remain separate, including `Set-Cookie`. An empty string is a valid value;
remove a field with `without()` instead of supplying an empty list. Values preserve
whitespace and reject control bytes except horizontal tab.

Request methods preserve their case. Request URIs preserve their encoded form and
reject empty values, whitespace, controls, and fragments. This is basic validation,
not a full URI parser. Response status codes must be between 100 and 599.

## Decode and return typed JSON

`Decoder<T>` describes a stateless decoder returning a concrete DTO. DTO mapping
and validation are explicit PHP and compile without reflection. The compiled
container example includes `CreateUserDecoder`, implementing
`Decoder<CreateUserInput>`, and a `CreatedUser` result with a typed `toArray()`.

An endpoint uses the generic request-body helper and explicitly mapped output:

```php
$input = JsonBody::decode(
    $request,
    fn (string $body): CreateUserInput => $this->decoder->decode($body),
);
$user = $this->users->create($input);

return JsonResponse::create($user->toArray(), 201, new Headers([
    'Location' => [$this->urls->generate('users.show', ['id' => $user->id])],
]));
```

The typed closure literal lets ElephC 0.27.6 infer the generic DTO return type.
`JsonBody` requires one `Content-Type` field containing `application/json` or an
application subtype with a `+json` suffix. Media names are case-insensitive and
parameters are ignored. Input bytes are decoded as UTF-8 without transcoding.

`JsonObject::decode` validates syntax with `JSON_THROW_ON_ERROR` at depth 64,
requires an object root, and exposes fields for explicit mapping. An empty object
is accepted; a root list or scalar is a validation failure. Nested values use
associative decoding. Numbers keep their PHP numeric types, so a large unquoted
number cannot pass a string-field check. Duplicate object keys use the last value.

`JsonInputMiddleware` maps only explicit `InputError` exceptions:

| Status | Error | Meaning |
| --- | --- | --- |
| `400` | `malformed_json` | Invalid JSON, UTF-8, or excessive nesting |
| `415` | `unsupported_media_type` | Missing, repeated, or unsupported content type |
| `422` | `invalid_input` | Valid JSON with an invalid shape or field values |

Validation responses include an `errors` map. Malformed-input responses expose
the stable error code; the original parser exception remains available as the
exception cause. Other application failures propagate through the existing
kernel cleanup path.

`JsonResponse::create` returns an immutable `Response` with `application/json`,
preserving the supplied status and other headers. Output is an explicitly mapped
array; lists remain lists and an empty array encodes as `[]`. Encoding uses
`JSON_THROW_ON_ERROR` at depth 64, so invalid UTF-8, non-finite numbers and excessive
nesting fail before a response is emitted. Encoding failures propagate as server
errors.

With the browser example running, create a user:

```sh
curl -i http://127.0.0.1:8080/users \
  -H 'Content-Type: application/json' \
  -H 'X-Request-Id: creator' \
  --data '{"name":"Ada"}'
```

The response is `201 Created`, includes `Location: /users/1`, and has the body
`{"id":1,"name":"Ada"}`. The decoder requires a string name, removes surrounding
ASCII spaces, accepts 1 to 100 UTF-8 bytes without ASCII controls, and rejects
unknown fields. The middleware and endpoint share the current request session.
Existing text responses retain their content type.

The example repository stores created names within its application container.
Multiple calls through one `Kernel\Application` share those names and incrementing
IDs. The current PHP and native HTTP entries create a new container per request;
durable storage across HTTP requests requires a persistent repository.

## Decode typed query input

Read query parameters from the immutable request URI:

```php
use Phex\Http\Query\QueryParameters;

$query = QueryParameters::fromRequest($request);
$page = $query->integer('page', default: 1);
$active = $query->boolean('active', default: false);
$sort = $query->get('sort', default: 'asc');
$tags = $query->values('tag');
```

`get`, `integer` and `boolean` require one value. Missing parameters use the
explicit default or raise `422 invalid_input`. A present empty value stays empty:
`get` returns it, while numeric and boolean readers reject it. Defaults apply only
to missing parameters. Repeated names are rejected by scalar readers even when
their values match. `values` returns every value in order, or an empty list for
a missing parameter. `has` distinguishes missing and empty values.

Integers use checked signed decimal conversion, also used by route parameters.
Leading zeros and negative zero are accepted; a leading plus, whitespace,
fractions, exponents and overflow are rejected. Booleans accept exactly `true`,
`false`, `1` and `0`, with case-sensitive spelling.

Parsing uses `&` separators, the first `=` in each pair, `+` for space and one
percent-decoding pass. Empty segments are skipped; a bare name has an empty value.
Names remain literal, including dots, spaces, numeric names and brackets.
`all()` returns ordered immutable `QueryParameter` objects with string `name`
and `value` properties. There is no implicit array nesting or name normalization.
The parser reads neither `$_GET` nor `parse_str`, whose name normalization and
configuration-dependent limits differ from this explicit contract. See the
[PHP parsing notes](https://www.php.net/manual/en/function.parse-str.php).

Malformed percent escapes and decoded ASCII control bytes produce
`400 malformed_query`. Values otherwise remain byte strings, including UTF-8
bytes; application decoders choose the accepted text encoding and domain shape.
The defaults allow 8,192 encoded query bytes and 100 nonempty pairs. Both limits
are explicit positive arguments to `decode` and `fromRequest`; exceeding either
produces `400 query_limit_exceeded`. These are Phex input rules, with stricter
escape/control handling than the browser's
[form URL-encoded parser](https://url.spec.whatwg.org/#urlencoded-parsing).

DTO decoders implement the existing `Decoder<T>` contract and receive the encoded
query string without its leading `?`. `QueryString::decode` preserves their
concrete result through a typed closure:

```php
$input = QueryString::decode(
    $request,
    fn (string $query): SearchInput => $this->decoder->decode($query),
);
```

`Http\Input\InputErrorMiddleware` maps explicit query and JSON input errors to
the same JSON response policy. Query field errors use names such as `query.page`.
Unexpected application and response-encoding failures propagate. The original
`Http\Json\InputError` factories and `JsonInputMiddleware` name remain available
through the shared error policy.

The application skeleton includes `SearchInput`, `SearchDecoder` and
`SearchAction`, with explicit container bindings and a `GET /search` route.
It returns validated example options, including range and sort validation:

```sh
curl 'http://127.0.0.1:8080/search?page=2&limit=10&active=true&sort=desc'
```

The response is `{"page":2,"limit":10,"active":true,"sort":"desc"}`. The route
works on development PHP, generated PHP and the native executable.

## Route requests

```php
use Phex\Examples\JsonApi\HealthEndpoint;
use Phex\Examples\JsonApi\UserEndpoint;
use Phex\Routing\Definition\Route;
use Phex\Routing\Matcher\TableMatcher;
use Phex\Routing\Router;

$router = new Router(new TableMatcher([
    new Route('GET', '/health', new HealthEndpoint()),
    new Route('GET', '/users/{id}', new UserEndpoint()),
]));
```

`Router` implements the existing HTTP `Handler` contract and can be the terminal
handler of a middleware pipeline. Routing endpoints implement
`Phex\Contracts\Routing\Endpoint`; they receive both `Request` and an immutable
`Parameters` object. Route parameters stay separate from the HTTP message.

`TableMatcher` parses and orders patterns at construction. Container builds emit a
specific matcher implementing the same `Matcher` contract, with prevalidated
segments and fixed search branches. Definitions use full-segment
placeholders such as `{id}`; names are ASCII identifiers and must be unique within
a pattern. Repeated structural patterns for the same method are rejected, even
when their placeholder names differ.

Among routes supporting the requested method, literal segments take precedence
over parameters at the first differing segment. A static route for another method
does not hide a compatible parameterized route. Methods and paths are case-sensitive.
`HEAD` and `OPTIONS` currently require explicit definitions.

The matcher accepts origin-form request targets (`/path?query`). Query strings are
ignored. Request paths are split before each segment is percent-decoded exactly
once: `a%2Fb` remains one parameter with value `a/b`, `%252F` becomes `%2F`, and `+`
remains `+`. Patterns use decoded literals. Empty segments, trailing slashes, and
dot segments remain significant; placeholders match one nonempty segment.
Malformed escapes and decoded control bytes produce `400`.

Missing paths produce `404`. A matching path without a compatible method produces
`405` with unique, lexicographically sorted methods in `Allow`.

Use `$parameters->get('id')` for a string or `$parameters->integer('id')` for a
checked signed decimal integer. Integer conversion accepts leading zeros, rejects
nondecimal syntax and overflow, and raises `InvalidParameter`, which the router
maps to `400`. A missing parameter raises `OutOfBoundsException`; unexpected
application exceptions propagate.

For a custom conversion, `Parameters::map` has a generic PHPDoc return type:

```php
$name = $parameters->map('name', fn (string $raw): string => strtoupper($raw));
```

Run the routing example with `php examples/json-api/main.php`. It demonstrates a
health endpoint, an integer user parameter, a missing path, and a rejected method.

## Generate URLs from named routes

Assign an optional name when defining a route:

```php
new EndpointRoute('GET', '/users/{id}', UserEndpoint::class, name: 'users.show');
```

Inject `Phex\Contracts\Routing\UrlGenerator` into an action or service, then
generate the path from its name:

```php
$path = $this->urls->generate('users.show', ['id' => 42]);
// /users/42
```

Names are case-sensitive and unique across the entire route table, including
different HTTP methods. They contain 1 to 128 ASCII characters, start with a
letter or underscore, and continue with letters, digits, underscores, dots or
hyphens. Unnamed routes keep their existing matching behavior. `Route` also
accepts a named `name` argument for explicitly assembled routing tables.

Parameters are named string or integer values. Every placeholder is required;
unknown routes, missing/extra parameters and invalid values raise
`UrlGenerationFailed`, a programming error that propagates through input
middleware. Zero and negative integers are accepted. Each value is encoded as
one path segment: `a/b` becomes `a%2Fb`, a space becomes `%20`, plus becomes
`%2B`, and an existing `%2F` value becomes `%252F`. Literal pattern segments use
the same byte encoding, with UTF-8 and other non-ASCII bytes percent-encoded.

Generated paths begin with a single slash. Named patterns cannot begin with
`//` or contain literal `.`/`..` segments; parameter values cannot be empty,
contain ASCII controls, or equal `.`/`..`. These constraints keep generated
references on the current origin and avoid the browser's
[dot-segment normalization](https://url.spec.whatwg.org/#single-dot-path-segment).
Interior empty segments and trailing slashes remain significant and are preserved.

`UrlGenerator` is a reserved application-scoped container dependency when routes
are configured. The generated container exposes it through `urls()`. PHP
development uses `TableUrlGenerator` with the same definitions. Compiled builds
emit `<Container>UrlGenerator` beside the matcher, with fixed name branches,
pre-encoded literal segments and direct parameter lookups. Generation performs
no runtime pattern parsing, reflection or class generation. Explicitly assembled
applications can construct `TableUrlGenerator` from their list of `Route` values.

The compiled-container example uses `users.show` for its `201` response Location.
The application skeleton names its home, greeting and search routes; `HomeAction`
returns a `Link: </search>; rel="search"` header through the injected generator.
Development route edits update that link on subsequent PHP requests. Generated
PHP artifacts and native binaries use the route definitions from their build.

## Manage the request lifecycle

`Kernel\Application` implements the HTTP `Handler` contract. For every call it
creates a `RequestScope`, calls a typed `HandlerFactory`, executes the resulting
handler, and closes the scope in `finally`. The application retains its shared
factory; request state stays in the scope and in newly constructed handlers.

Register resources implementing `ScopeResource` with `$scope->own($resource)`.
The generic PHPDoc return type preserves the resource's concrete type. Resources
close in reverse registration order. Registering the same object twice still
closes it once. Closing is idempotent, including reentrant calls, and registration
after closure begins raises `LogicException`.

Every resource is attempted when cleanup throws. `CleanupFailed` exposes its
typed first cause and the complete failure list. Its previous exception is the
request failure when one exists, otherwise the first cleanup failure. Factory and
handler exceptions propagate unchanged when cleanup succeeds.

The `request-lifecycle` example shares its immutable router and creates a trace
resource and middleware per request:

```sh
php examples/request-lifecycle/main.php
```

Factories must keep request state local. The current lifecycle closes before
returning an eager string response; streaming will require a separate completion
contract.

## Compile a dependency container

Service definitions use generic PHPDoc types and explicit constructor or public
static factory providers. Bind each dependency by its class or interface. Named
entries become public methods with concrete return types:

```php
use Phex\Build\Compiler\BuildConfig;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Examples\CompiledContainer\Connection;
use Phex\Examples\CompiledContainer\MemoryUserRepository;
use Phex\Examples\CompiledContainer\UserRepository;

return new BuildConfig(new Container([
    Service::constructor(Connection::class, Lifetime::Application),
    Service::constructor(UserRepository::class, Lifetime::Application, MemoryUserRepository::class),
], ['repository' => UserRepository::class]),
    'App\Generated\ApplicationContainer',
    __DIR__.'/var/generated/ApplicationContainer.php',
);
```

Save this trusted build configuration as `container.php` and run
`php bin/phex build container.php`. Load the generated file in the application
bootstrap or register its namespace with Composer. Build artifacts are ordinary
PHP with direct constructor/static factory calls and typed caches. Reflection
runs in the build tool or the separate PHP development bootstrap. Generated
containers never evaluate source, generate classes, inspect constructors, or
resolve arbitrary class names.

The three lifetimes are:

- `Application`: constructed at startup and shared until the container closes.
- `Request`: constructed on first use and cached within one `RequestScope`.
- `Transient`: constructed on every resolution and owned by the scope constructing it.

All application services are initialized before the container constructor returns.
`$container->request($scope)` reuses one generated context per container identity
and scope. Request caches live in that context, retained by the request scope.
Named request entries are accessed through this context; application entries are
also available directly on the application container.

The compiler rejects missing/duplicate bindings, dependency cycles, unsupported
provider signatures, and application services that capture request state, including
through transient dependencies. Object parameters require explicit bindings;
nullable parameters still resolve their declared dependency. Scalar/array parameters
need literal defaults or an explicit factory. Union, intersection, variadic, and
by-reference dependencies require an explicit factory. Configuration objects and
environment reads can be encapsulated in that factory.

Resources implementing `ScopeResource` are registered automatically, including
objects returned behind factory interfaces. Request resources close through the
kernel's existing `finally` path. Application resources close when the host calls
`$container->close()`, after all requests finish. Startup failures release resources
already created. Cleanup follows the same LIFO and failure-chain policy as request
scopes. Transient dependencies of application services belong to the application;
transients resolved through a request context belong to that request.

`EndpointRoute` binds a route to an endpoint service type. The emitted application
container constructs its generated matcher once. `EndpointResolver` resolves the
matched endpoint in the existing request context. An optional handler service can
compose middleware around `Router`, using the built-in `Matcher` and
`EndpointResolver` dependencies. `Request`, `RequestScope`, and `ApplicationScope`
are also reserved built-in dependencies. A generated web container implements
`HandlerFactory` and plugs directly into `Kernel\Application`.

The generated `<Container>Matcher` is ordinary typed PHP in the same artifact as
the containers. Build-time analysis validates patterns and ambiguous method/shape
pairs, extracts segment metadata, and emits segment-count dispatch with literal
branches before parameter branches. Method-specific names are captured directly
into `Parameters`. A literal route with an incompatible method still permits a
compatible parameter route. Unique `Allow` methods are ordered during generation;
matching uses local boolean flags without sorting methods or rebuilding a tree.

Generated `Route` objects receive prevalidated segment arrays. Their `PathPattern`
objects preserve matching and shape/precedence operations while skipping pattern
parsing at startup. The optional `compiledSegments` inputs are internal hooks for
trusted build artifacts; ordinary constructors retain their existing validation.
Incoming request paths still use the shared percent-decoding and validation rules.
`TableMatcher` remains available for explicitly assembled routers and serves as
the reference for differential compatibility tests.

The complete example shares a repository, reuses a request session between
middleware and endpoint, and creates transient endpoints:

```sh
composer generate
php examples/compiled-container/main.php
```

It prints `200 user-7 alice` and `200 user-42 bob`. The generated files live under
ignored `var/generated/`. Generation is deterministic, skips unchanged artifacts,
and replaces each complete file atomically after validation. Run the build before
starting compiled PHP hosts or invoking ElephC; the compiled runtime never loads
build configuration.

## Try it in the browser

From the repository root:

```sh
composer install
php -S 127.0.0.1:8080 -t examples/compiled-container/public examples/compiled-container/public/index.php
```

Open [http://127.0.0.1:8080/users/7](http://127.0.0.1:8080/users/7).
The response is `user-7`. A request with an explicit ID demonstrates the shared
middleware and endpoint session:

```sh
curl -i -H 'X-Request-Id: alice' http://127.0.0.1:8080/users/7
```

Both `X-Request-Id` and `X-Middleware-Id` contain `alice`. `/missing` returns
`404`, `/users/nope` returns `400`, and `POST /users/7` returns `405` with
`Allow: GET`. `HEAD` routes still require explicit definitions; the emitter
suppresses content even for a `HEAD` error response. Stop the development server
with Ctrl+C. PHP's built-in server and its router script are described in the
[PHP manual](https://www.php.net/manual/en/features.commandline.webserver.php).

The development front controller uses Composer and `DevelopmentContainer` with
the existing kernel. It reads the same `container.php` definitions, validates them
with the build's `Analyzer`, and uses `TableMatcher`. Constructor and factory
metadata are inspected at bootstrap; service resolution uses the validated data
and constructs existing classes. It performs no `eval`, class generation, or
artifact writes. A fresh checkout needs no `composer generate` to serve PHP.

Changes to routes, bindings, provider signatures, and application code are loaded
on subsequent PHP requests. If OPcache is enabled, development settings must
validate timestamps; `opcache.revalidate_freq=0` makes edits visible on the next
request. Persistent PHP workers will need a restart or a future watcher to reload
class declarations.

Both backends require the same explicit bindings and reject invalid dependency
graphs and lifetimes. Development retains eager application services, isolated
request caches, transient ownership, and deterministic cleanup. It implements the
same `HandlerFactory` contract as a generated web container. For development-only
composition or diagnostics, `get(Foo::class)` has a generic return type; request
and transient services must be obtained through `$container->request($scope)`.
Application handlers depend on shared contracts and services.

The compiled PHP front controller remains available explicitly:

```sh
composer generate
php -S 127.0.0.1:8080 -t examples/compiled-container/public examples/compiled-container/public/compiled.php
```

`compiled.php` loads the generated container through the static bootstrap. It
shares the generated runtime with ElephC and loads no development backend.

`GlobalsRequestReader` reads `php://input`; `ServerRequest` maps the method, raw
URI, `HTTP_*` fields and CGI content metadata into immutable messages. Values
already combined by SAPI remain single header values. Application services close
before response emission. Invalid transport metadata becomes `400`; unexpected
application failures produce a generic `500` and are logged by the example.

`BufferedResponseEmitter` writes through the typed `ResponseOutput` boundary;
`SapiOutput` handles PHP status, header and output functions. Repeated response
fields remain separate, including cookies. Buffered content lengths are computed
from actual bytes, and transfer-encoding/trailer fields are removed. `HEAD` sends
no body; informational, `204`, `205`, `304` and successful `CONNECT` responses also
suppress content. Length is omitted where inappropriate and is zero for `205`,
following [HTTP response semantics](https://www.rfc-editor.org/rfc/rfc9110.html#name-content-length).
Streaming, informational response sequences, and connection upgrades/tunnels need
separate transport support.

For PHP-FPM, configure the web server to use `examples/compiled-container/public`
as its document root. Use `index.php` as the development front controller or
`compiled.php` after generation for the compiled path. The same SAPI adapter is
used. The same socket checks exercise development PHP, compiled PHP, and ElephC's
native web server.

## Run the native HTTP server

From the repository root:

```sh
composer build:web
./var/build/phex-web --listen 127.0.0.1:8080 --workers 1
```

Open [http://127.0.0.1:8080/users/7](http://127.0.0.1:8080/users/7) or use the
same curl command as above. The native server serves `user-7`, including the same
request ID headers and routing error responses. Stop it with Ctrl+C.

`build:web` generates the container and compiles the example's `native.php`
entry point with `elephc --web --strict-php`. The build wrapper and executable
stay under ignored `var/build/`. PHP and Composer are used during the build;
the resulting server runs as a native executable. The build helper accepts
`--source` and `--output` for test entry points; output must stay inside `var/`.

The compiled PHP and native entry points load explicit declarations through `http-bootstrap.php`,
without runtime Composer loading. `WebApplication::serve` has generic PHPDoc
parameters bounded by `RequestReader` and `ResponseOutput`, and shares the
container, kernel, middleware, router, endpoint and response policy. The PHP
entry uses `GlobalsRequestReader`/`SapiOutput`; the native entry uses
`NativeRequestReader`/`NativeOutput`. Both outputs share header/status emission.

ElephC 0.27.6 returns `false` for an empty `php://input` body and does not provide
`headers_sent()`. The native adapter normalizes empty input and relies on the
default web bridge's buffered output. These differences stay at the runtime
boundary. No native-only syntax is needed in shared PHP sources.

`native.php` has no reference to `Runtime/Development`, build analysis, or Composer
loading. The development entry is separate, with no runtime environment branch in
the native path. HTTP integration checks also compile and run a copied native
distribution where development sources, build sources, build configuration, and
`vendor` are absent. An accidental dependency across that boundary fails the check.

The initial native target uses ElephC's default `worker` isolation. Every native
isolation mode reruns the entry point and resets PHP globals, function statics,
static properties and the PHP heap for each request:

| Isolation | Process executing PHP | PHP application state |
| --- | --- | --- |
| `worker` | Reused web worker | Reset for each request |
| `pool` | Reused handler child | Reset for each request |
| `request` | New handler child for each request | Reset for each request |

`opcache.preload` runs once in the master before workers are forked. Its output
goes to the server log, and PHP globals assigned there do not survive request
initialization. It cannot retain an application container. Native bridge caches
outside the PHP heap can have a longer lifetime.

ElephC 0.27.6 therefore requires this example to create and close its application
container per invocation, as the PHP front controller does. Retaining a PHP
container across native requests requires compiler/runtime support for managed
application objects and worker lifecycle hooks. A Phex request loop alone cannot
provide that lifetime. Persistent PHP workers remain a separate host adapter.
Isolation modes and server options are described in the
[ElephC web documentation](https://github.com/illegalstudio/elephc/blob/v0.27.6/docs/beyond-php/web.md).

## Static analysis

```sh
composer tools:install
composer analyse
```

PHPStan runs at `level: max` against all framework sources, tests, examples, the CLI,
the application skeleton and generated containers,
with PHP 8.3 as the language target. There is no baseline or ignored-error list.
PHPUnit extensions provide assertion-aware type checking for the shared tests.

Analysis dependencies have their own Composer manifest and lockfile under
`tools/phpstan/`. This keeps PHPStan's PHAR bootstrap outside the ElephC input
graph. Its cache lives under the ignored `var/cache/phpstan/` directory.

Run the complete required checks with:

```sh
composer check
```

CI installs both dependency sets and runs this same command on PHP 8.3 and 8.5,
including local HTTP integration checks.

## Test both runtimes

```sh
composer test
```

The command generates containers, runs compiler and development-backend tests on PHP, and runs
the same runtime PHPUnit cases under PHP and under `elephc test` with
`--strict-php`. It compares JUnit test identities and outcomes, fails on missing
cases or any failed or skipped case, and removes temporary reports on exit.
Both toolchains are required; a missing native compiler fails the command. It also
compiles and runs all four examples and checks their output against PHP. A bounded
HTTP check starts temporary PHP and native ElephC servers and runs the same socket
cases against the compiled application and transport fixture. Native binaries,
reports and wrappers are removed on success or failure, along with the test
servers and their worker processes.

Development HTTP checks run in a relocated copy with real Composer dependencies
and no generated artifacts. They edit routes and introduce a new service provider
while the PHP server is running, then verify that the next requests use the new
definitions without building or restarting. The native isolation check uses a
separate distribution that omits development and build code entirely.

`composer test:native-lifecycle` characterizes the pinned native runtime with
consecutive HTTP requests in `worker`, `pool` and `request` isolation. It checks
process reuse separately from globals, function statics, cached application
objects and fresh request metadata. A preload probe checks master startup and
worker recycling. The fixture uses text output because direct `json_encode`
output separately reproduces a handler crash in ElephC 0.27.6 `pool` mode.
These checks characterize lifecycle behavior; the application target remains
default `worker`. Both `composer check` and `composer test` include this
characterization.

`composer test:application` creates a project outside this checkout, installs a
mirrored package without Packagist access, removes its original package source,
and verifies development PHP, generated PHP and native HTTP behavior. It tests
live edits, declaration ordering, invalid builds preserving prior artifacts,
failed native compilation cleanup, PHPStan max on the installed application, and
relocation. A second compilation runs after removing Composer loading,
development/build code and configuration from the installed project. The same
GET, typed POST and error checks pass throughout; temporary projects and servers
are removed on success and failure.

For a single runtime:

```sh
composer test:php
composer test:elephc
```

The shared cases cover header validation and immutability, middleware order and
short-circuiting, retries, reuse after an exception, routing precedence, path
encoding, parameter conversions, scope cleanup, combined failures, interleaved
Fiber requests, generated service lifetimes, constructor-failure cleanup, scoped
endpoint resolution, and all four examples. Compiler tests exercise graph failures,
factory signatures, deterministic output, literal escaping, and atomic replacement.
HTTP cases cover CGI metadata validation, raw URI and binary body preservation,
header mapping, response framing, repeated fields, and content suppression.
JSON cases cover typed DTO decoding, field validation, media types, root shape,
UTF-8 and escapes, bounded nesting, encoding failures, scoped creation, and
recovery after rejected input. The same POST/error socket checks run on
development PHP, compiled PHP, native ElephC, and the isolated native distribution.
The compiled matcher is compared with `TableMatcher` across methods, overlapping
patterns, method-specific captures, numeric literals/methods, encoded bytes, empty
segments, query strings, malformed input, and interleaved calls. The compiled
container example uses the generated matcher in the real PHP/native HTTP checks.
