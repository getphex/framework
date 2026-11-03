# URL generation

[Documentation](README.md)

Named routes keep links tied to route definitions. Change a named path, rebuild when needed, and its consumers continue generating the configured path.

## On this page

- [Name a route](#name-a-route)
- [Inject the generator](#inject-the-generator)
- [Use a generated location](#use-a-generated-location)
- [Encoding and failures](#encoding-and-failures)
- [Development and compiled paths](#development-and-compiled-paths)

## Name a route

The [Quickstart](quickstart.md) declares:

```php
new EndpointRoute('GET', '/hello/{name}', HelloAction::class, name: 'hello.show'),
```

Route names are unique across methods. They are case-sensitive identifiers of at most 128 ASCII bytes. See [Routing](routing.md#named-routes) for validation rules.

## Inject the generator

Add `Phex\Contracts\Routing\UrlGenerator` to a consumer's constructor. The container supplies it for applications with a nonempty configured route table; no extra service binding is needed.

Generate paths with explicit parameters:

```php
$path = $this->urls->generate('hello.show', ['name' => 'Ada Lovelace']);
// /hello/Ada%20Lovelace
```

Values are strings or integers. Zero and negative integers are supported. Parameter names must exactly match the pattern; passing an extra key is a programming error.

## Use a generated location

For an application's named `users.show` route:

```php
$location = $this->urls->generate('users.show', ['id' => $user->id]);
return JsonResponse::create(
    ['id' => $user->id, 'name' => $user->name],
    201,
    new Headers(['Location' => [$location]]),
);
```

Import `JsonResponse` from `Phex\Http\Json` and `Headers` from `Phex\Http\Message`. The SQLite [CRUD tutorial](database-tutorial.md) uses this response pattern.

## Encoding and failures

Generated paths are references on the current origin, beginning with a single slash. Each value is encoded as one segment:

| Value | Encoded segment |
| --- | --- |
| `a/b` | `a%2Fb` |
| `a+b` | `a%2Bb` |
| `%2F` | `%252F` |
| `Ada Lovelace` | `Ada%20Lovelace` |

UTF-8 bytes are percent-encoded without normalization. Controls, empty values and dot/dot-dot segments are rejected. Unknown names, missing parameters, extra keys and invalid values raise `UrlGenerationFailed`; input-error middleware does not turn these programming errors into validation responses.

URL generation does not append a query string or infer a host. Add application-specific query handling explicitly when needed.

## Development and compiled paths

PHP development uses `TableUrlGenerator` over the validated route definitions. Builds emit a fixed generated URL generator with pre-encoded literals and parameter lookups. Both implement the same contract and preserve the same path semantics.

The skeleton's home action demonstrates this dependency by returning a named link to `/search`. Route edits in development apply on the next request; generated PHP and native builds use their compiled definitions.

---

[Previous: Query strings](query-strings.md) | [Next: Structured logging](logging.md)
