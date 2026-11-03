# Routing

[Documentation](README.md)

Routes map an HTTP method and path to a typed endpoint. Define them once in `routes/web.php`; development PHP and generated matchers use the same definitions.

## On this page

- [Define a route](#define-a-route)
- [Path parameters](#path-parameters)
- [Encoding and query strings](#encoding-and-query-strings)
- [Precedence and method errors](#precedence-and-method-errors)
- [Named routes](#named-routes)
- [Explicit routing without a container](#explicit-routing-without-a-container)

## Define a route

Add an entry to the skeleton's returned route array:

```php
new EndpointRoute('GET', '/hello/{name}', \App\Http\Action\HelloAction::class, name: 'hello.show'),
```

`EndpointRoute` is `Phex\Container\Definition\EndpointRoute`. Register the endpoint's class in `config/services.php` as shown in the [Quickstart](quickstart.md).

Each endpoint implements `Phex\Contracts\Routing\Endpoint`:

```php
public function handle(Request $request, Parameters $parameters): Response;
```

Here the message types come from `Phex\Http\Message` and route parameters from `Phex\Routing\Parameter`. The router resolves the endpoint through the current request container, so it receives that request's dependencies.

## Path parameters

Placeholders occupy an entire segment and have unique ASCII identifier names. For a registered application `TeamUserAction` endpoint, a multiple-capture route can be written as:

```php
new EndpointRoute('GET', '/teams/{team}/users/{user}', TeamUserAction::class),
```

Inside the endpoint:

```php
$team = $parameters->get('team');
$userId = $parameters->integer('user');
```

`get` returns the decoded string. `integer` accepts checked signed decimal input, including leading zeros, and rejects fractions, whitespace, plus signs and overflow. Invalid conversion becomes `400`; accessing a missing parameter is a programming error.

`map` preserves the result of an explicitly typed converter:

```php
$name = $parameters->map('name', fn (string $value): string => strtoupper($value));
```

Placeholders capture nonempty segments. Literal matching is case-sensitive. Repeated and trailing slashes are significant; `/users` and `/users/` are different patterns.

## Encoding and query strings

The matcher splits the raw path on `/` before decoding each segment once:

| Encoded segment | Captured value |
| --- | --- |
| `Ada%20Lovelace` | `Ada Lovelace` |
| `a%2Fb` | `a/b`, still one capture |
| `a+b` | `a+b` |
| `%252F` | `%2F` |

Malformed percent escapes and decoded control bytes produce `400`. The router expects an origin-form target such as `/users/7?active=true`. It ignores the query while matching; parse it explicitly with [QueryParameters](query-strings.md).

## Precedence and method errors

At the first differing segment, a literal takes precedence over a parameter. A literal route for a different method does not hide a matching parameter route for the requested method.

Structurally identical patterns for the same method are rejected, even if their placeholder names differ. Define separate methods explicitly. `HEAD` and `OPTIONS` do not automatically inherit `GET` behavior.

| Situation | Response |
| --- | --- |
| No matching path | `404 Not Found` |
| Path matches but method does not | `405 Method Not Allowed`, with sorted unique `Allow` methods |
| Malformed target or invalid integer capture | `400 Bad Request` |

Unexpected endpoint failures follow the host's generic `500` policy and request cleanup.

## Named routes

Names are optional, case-sensitive and unique across methods. They contain at most 128 ASCII bytes, beginning with a letter or underscore and continuing with letters, digits, underscores, dots or hyphens.

Named patterns reject literal dot/dot-dot segments and initial double slashes to keep generated references on the current origin. [URL generation](urls.md) explains parameter encoding and failure behavior.

## Explicit routing without a container

For small compositions, build ordinary `Route` values with endpoint instances and pass them to `TableMatcher` and `Router`. Container-backed applications use `EndpointRoute` definitions and receive a generated matcher during the build.

Both paths share precedence, parameter and error semantics. Compiled routing prevalidates patterns and emits fixed matching branches; no request-time route generation is needed.

---

[Previous: Configuration](configuration.md) | [Next: Middleware](middleware.md)
