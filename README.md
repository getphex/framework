# Phex

A PHP web framework designed for native ElephC compilation.

Phex is in its first implementation increment. The HTTP core provides immutable
requests, responses, and headers, typed handler and middleware contracts, and an
explicit middleware pipeline. Shared sources run directly on PHP and compile with
ElephC. Generic PHPDoc annotations specialize the pipeline assembly method while
the runtime pipeline exposes the shared handler contract.

## Requirements

- PHP 8.3 or newer within PHP 8.x.
- Composer 2.
- ElephC 0.27.6 for native compilation and compatibility tests.
- Python 3.10 or newer for the compatibility checker.

## Try the example

```sh
composer install
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
bytes. Streams, transport adapters, routing, request scopes, and the build tool are
subsequent increments.

`Headers` accepts a map of names to nonempty lists of string values. Names are
case-insensitive and exported in lowercase. Repeated values preserve their order
and remain separate, including `Set-Cookie`. An empty string is a valid value;
remove a field with `without()` instead of supplying an empty list. Values preserve
whitespace and reject control bytes except horizontal tab.

Request methods preserve their case. Request URIs preserve their encoded form and
reject empty values, whitespace, controls, and fragments. This is basic validation,
not a full URI parser. Response status codes must be between 100 and 599.

## Test both runtimes

```sh
composer test
```

The command runs the same PHPUnit cases under PHP and under `elephc test` with
`--strict-php`. It compares JUnit test identities and outcomes, fails on missing
cases or any failed or skipped case, and removes temporary reports on exit.
Both toolchains are required; a missing native compiler fails the command. It also
compiles and runs the JSON example and checks its output against PHP.

For a single runtime:

```sh
composer test:php
composer test:elephc
```

The shared cases cover header validation and immutability, middleware order and
short-circuiting, retries, reuse after an exception, interleaved Fiber requests,
and the JSON example.
