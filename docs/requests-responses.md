# Requests and responses

[Documentation](README.md)

Phex passes immutable HTTP messages between the runtime host, middleware and endpoints. Request and response bodies are buffered strings and preserve binary bytes.

## On this page

- [Read a request](#read-a-request)
- [Construct and update messages](#construct-and-update-messages)
- [Return a response](#return-a-response)
- [Header values](#header-values)
- [Transport output](#transport-output)

## Read a request

An endpoint receives `Phex\Http\Message\Request` with readonly properties:

```php
$method = $request->method;
$target = $request->uri;
$body = $request->body;
$types = $request->headers->values('Content-Type');
```

The URI keeps its encoded form, including the query. Methods preserve their case. Empty targets, fragments, whitespace and control bytes are rejected during message construction; routing applies its additional [path rules](routing.md).

There is no implicit population of DTOs from request globals. Use [JSON](json.md), [query readers](query-strings.md) and route parameters explicitly.

## Construct and update messages

For an application test:

```php
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;

$request = new Request(
    'POST',
    '/greet',
    new Headers(['Content-Type' => ['application/json']]),
    '{"name":"Ada"}',
);
```

`withMethod`, `withUri`, `withHeaders` and `withBody` return updated messages. Keep the returned value:

```php
$forwarded = $request->withBody('replacement');
```

The original request remains unchanged. Response updates use `withStatus`, `withHeaders` and `withBody` in the same way.

## Return a response

```php
use Phex\Http\Message\Headers;
use Phex\Http\Message\Response;

return new Response(
    status: 201,
    headers: new Headers(['Content-Type' => ['text/plain; charset=utf-8']]),
    body: 'Created',
);
```

Statuses must be between 100 and 599. The web host supplies `text/plain; charset=utf-8` when a response has no content type. Use [JsonResponse](json.md#return-json) for explicit JSON payloads.

## Header values

`Headers` accepts names mapped to nonempty lists of string values:

```php
$headers = new Headers([
    'Set-Cookie' => ['first=1; Path=/', 'second=2; Path=/'],
    'Location' => ['/users/1'],
]);
$headers = $headers->withAdded('Vary', ['Accept']);
$headers = $headers->without('Location');
```

Lookups are case-insensitive. Names are exported in lowercase and repeated values keep their order. An empty string is a valid value; an empty value list is not. `with` replaces the values for a name, `withAdded` appends values, and `without` removes the field.

Field names use HTTP token syntax. Values reject control bytes other than horizontal tab. Keep repeated `Set-Cookie` values separate rather than combining them into a comma-separated string.

## Transport output

The buffered emitter derives content length from actual bytes and removes transfer-encoding/trailer metadata. It suppresses body bytes for `HEAD` and bodyless statuses, including `204`, `205` and `304`, with status-specific length rules.

The emitter does not create an implicit `HEAD` route. Register the method in your route table if the endpoint must handle it. Streaming, trailers, informational response sequences and connection upgrades require transport support outside the current buffered API.

---

[Previous: Middleware](middleware.md) | [Next: JSON input and output](json.md)
