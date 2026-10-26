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
before returning the response.

## Requirements

- PHP 8.3 or newer within PHP 8.x.
- Composer 2.
- ElephC 0.27.6 for native compilation and compatibility tests.
- Python 3.10 or newer for the compatibility checker.

On Linux, native builds also need an assembler, linker, libc development files,
and the bzip2 development library (`libbz2-dev` on Debian and Ubuntu).

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
bytes. Streams, transport adapters, and the build tool are
subsequent increments.

`Headers` accepts a map of names to nonempty lists of string values. Names are
case-insensitive and exported in lowercase. Repeated values preserve their order
and remain separate, including `Set-Cookie`. An empty string is a valid value;
remove a field with `without()` instead of supplying an empty list. Values preserve
whitespace and reject control bytes except horizontal tab.

Request methods preserve their case. Request URIs preserve their encoded form and
reject empty values, whitespace, controls, and fragments. This is basic validation,
not a full URI parser. Response status codes must be between 100 and 599.

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

`TableMatcher` parses and orders patterns at construction. A future generated
matcher can implement the same `Matcher` contract. Definitions use full-segment
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

## Static analysis

```sh
composer tools:install
composer analyse
```

PHPStan runs at `level: max` against all framework sources, tests, and examples,
with PHP 8.3 as the language target. There is no baseline or ignored-error list.
PHPUnit extensions provide assertion-aware type checking for the shared tests.

Analysis dependencies have their own Composer manifest and lockfile under
`tools/phpstan/`. This keeps PHPStan's PHAR bootstrap outside the ElephC input
graph. Its cache lives under the ignored `var/cache/phpstan/` directory.

Run the complete required checks with:

```sh
composer check
```

CI installs both dependency sets and runs this same command on PHP 8.3 and 8.5.

## Test both runtimes

```sh
composer test
```

The command runs the same PHPUnit cases under PHP and under `elephc test` with
`--strict-php`. It compares JUnit test identities and outcomes, fails on missing
cases or any failed or skipped case, and removes temporary reports on exit.
Both toolchains are required; a missing native compiler fails the command. It also
compiles and runs all three examples and checks their output against PHP.

For a single runtime:

```sh
composer test:php
composer test:elephc
```

The shared cases cover header validation and immutability, middleware order and
short-circuiting, retries, reuse after an exception, routing precedence, path
encoding, parameter conversions, scope cleanup, combined failures, interleaved
Fiber requests, and all three examples.
