# Middleware

[Documentation](README.md)

Middleware wraps a typed handler. It can transform an immutable request, inspect or transform the response, or return early.

## On this page

- [Write middleware](#write-middleware)
- [Register and compose it](#register-and-compose-it)
- [Understand order](#understand-order)
- [Transform a request or return early](#transform-a-request-or-return-early)
- [Choose a safe lifetime](#choose-a-safe-lifetime)

## Write middleware

Create `app/Http/Middleware/PoweredBy.php`:

```php
<?php

/**
 * Adds a response header around downstream request handling.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace App\Http\Middleware;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Http\Middleware;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

final readonly class PoweredBy implements Middleware
{
    public function process(Request $request, Handler $next): Response
    {
        $response = $next->handle($request);

        return $response->withHeaders($response->headers->with('X-Phex', ['enabled']));
    }
}
```

The returned response contains `X-Phex: enabled`. This middleware keeps no current request or traversal cursor, so application lifetime is appropriate.

## Register and compose it

Add this entry to the existing service list in `config/services.php`:

```php
Service::constructor(\App\Http\Middleware\PoweredBy::class, Lifetime::Application),
```

In `App\Http\Handlers`, import `App\Http\Middleware\PoweredBy` and add it to the factory signature. Replace the existing `create` method with:

```php
public static function create(
    Matcher $matcher,
    EndpointResolver $resolver,
    RequestLoggingMiddleware $logging,
    PoweredBy $poweredBy,
): Handler {
    return Pipeline::wrap(
        $logging,
        Pipeline::wrap($poweredBy, Pipeline::wrap(new InputErrorMiddleware(), new Router($matcher, $resolver))),
    );
}
```

The other imported types are already present in the skeleton's factory. Phex resolves the new dependency from its typed signature. Regenerate compiled PHP or native builds after this provider change; PHP development uses it on the next request.

## Understand order

The outer middleware sees the request first and the response last:

```text
Request:  logging → powered-by → input-errors → router
Response: logging ← powered-by ← input-errors ← endpoint
```

An input validation failure becomes a response inside `InputErrorMiddleware`, so the outer middleware can still add its header. An unexpected exception propagates to the host's error reporter and cleanup boundary.

The generic `Pipeline::wrap` method preserves typed assembly while the resulting pipeline implements the shared `Handler` contract. Use typed middleware and handler instances explicitly; there is no global middleware cursor.

## Transform a request or return early

Within `process`, use immutable updates:

```php
$forwarded = $request->withHeaders($request->headers->with('X-Source', ['application']));
return $next->handle($forwarded);
```

For an early response, return a `Response` without calling `$next`. The rest of the pipeline is not invoked. Middleware may also call the next handler multiple times; each call traverses the remaining pipeline independently.

## Choose a safe lifetime

Middleware that holds a request logger, a database session or other request data must have request lifetime. Application middleware may hold immutable shared configuration. The [container lifetime rules](container.md#lifetime-rules) apply to middleware factories as well as endpoints.

---

[Previous: Routing](routing.md) | [Next: Requests and responses](requests-responses.md)
