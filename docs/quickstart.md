# Your first endpoint

[Documentation](README.md)

This tutorial adds `GET /hello/{name}` to the application created in [Installation](installation.md). It introduces a typed endpoint, constructor injection and a named route. All paths and commands below are relative to **your application**, not the framework checkout.

## On this page

- [Create a service](#create-a-service)
- [Create an endpoint](#create-an-endpoint)
- [Register the services](#register-the-services)
- [Register the route](#register-the-route)
- [Try the endpoint on PHP](#try-the-endpoint-on-php)
- [Use the same code in a build](#use-the-same-code-in-a-build)
- [Continue learning](#continue-learning)

## Create a service

Create `app/Support/Greeter.php`:

```php
<?php

/**
 * Builds greeting messages without retaining request state.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace App\Support;

final readonly class Greeter
{
    public function greet(string $name): string
    {
        return 'Hello, '.$name.'!';
    }
}
```

The service has no request data or mutable counters, so one instance can safely be shared for the application's lifetime.

## Create an endpoint

Create `app/Http/Action/HelloAction.php`:

```php
<?php

/**
 * Returns a greeting for the name captured by the hello route.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace App\Http\Action;

use App\Support\Greeter;
use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;

final readonly class HelloAction implements Endpoint
{
    public function __construct(private Greeter $greeter) {}

    public function handle(Request $request, Parameters $parameters): Response
    {
        return JsonResponse::create([
            'message' => $this->greeter->greet($parameters->get('name')),
        ]);
    }
}
```

The router supplies the immutable request and its decoded path parameters. The endpoint receives `Greeter` through its constructor and maps the result to explicit JSON output.

## Register the services

In `config/services.php`, add these entries inside the existing `new Container([...])` service list. Keep the skeleton's existing entries:

```php
Service::constructor(\App\Support\Greeter::class, Lifetime::Application),
Service::constructor(\App\Http\Action\HelloAction::class, Lifetime::Transient),
```

`Service` and `Lifetime` are already imported by the skeleton. Every service is registered explicitly. Phex validates the graph before interpreted or generated execution.

## Register the route

In `routes/web.php`, add this entry to the returned array:

```php
new EndpointRoute(
    'GET',
    '/hello/{name}',
    \App\Http\Action\HelloAction::class,
    name: 'hello.show',
),
```

`EndpointRoute` is already imported. The route name will also let other endpoints generate links without hard-coding `/hello/`.

## Try the endpoint on PHP

With `composer serve` running:

```sh
curl http://127.0.0.1:8080/hello/Ada
curl http://127.0.0.1:8080/hello/Ada%20Lovelace
```

The first request returns:

```json
{"message":"Hello, Ada!"}
```

The encoded name in the second request returns:

```json
{"message":"Hello, Ada Lovelace!"}
```

These edits take effect on subsequent PHP development requests without a build. Each placeholder captures one nonempty path segment. See [Routing](routing.md#path-parameters) for encoded slashes and checked integer conversions.

## Use the same code in a build

Stop the PHP development server, then run:

```sh
composer build
php -S 127.0.0.1:8080 -t public public/compiled.php
```

The same requests now pass through the generated container and matcher. To compile a native server instead:

```sh
composer build:native
./var/build/start --listen 127.0.0.1:8080 --workers 1
```

The service, endpoint, route and response code stay the same. Rebuild generated artifacts after changes to bindings, route definitions or declaration structure. Native source changes require recompilation.

## Continue learning

Read [The service container](container.md) to choose dependency lifetimes, [Middleware](middleware.md) to wrap endpoints and [URL generation](urls.md) to use `hello.show`.

---

[Previous: Installation](installation.md) | [Next: Directory structure](structure.md)
