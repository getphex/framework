# Structured logging

[Documentation](README.md)

Phex writes explicit structured records through a transport-independent logger. The skeleton already wires request correlation, completion logging and unexpected-error reporting.

## On this page

- [Write an application record](#write-an-application-record)
- [Request correlation](#request-correlation)
- [Completion and failure policy](#completion-and-failure-policy)
- [Outputs and severity](#outputs-and-severity)
- [Keep lifetimes separate](#keep-lifetimes-separate)

## Write an application record

Inject `Phex\Contracts\Logging\Logger` into a request service or endpoint:

```php
use Phex\Logging\Level;
use Phex\Logging\Record;

$this->logger->log(new Record(Level::Info, 'user.created', [
    'user_id' => $user->id,
]));
```

Context keys must be strings; values are strings, integers, floats, booleans or null. Convert DTOs to the specific scalar fields you intend to log. Objects and arbitrary nested data are not implicitly serialized.

## Request correlation

The standard hosts generate a fresh trusted `X-Request-Id` and include it in the response. A client-supplied value does not become the trusted correlation ID.

`RequestLogger` adds `request_id` and `method` to records. These fields come from the request context and override conflicting caller values. `JsonLogger` adds UTC `timestamp`, `level` and `message` fields.

A completion record has this shape:

```json
{"status":200,"request_id":"0123456789abcdef0123456789abcdef","method":"GET","timestamp":"2026-11-03T12:00:00Z","level":"info","message":"http.request.completed"}
```

The identifier and timestamp above are illustrative. Field order is not part of the logging contract.

## Completion and failure policy

`RequestLoggingMiddleware` writes an info record after downstream handling returns a response. The runtime error reporter writes unexpected exceptions at error level with `exception_type` and available request context. It returns a generic `500` body to the client.

Request bodies, authorization headers, cookies and full query strings are not automatically added to records. Deliberately logged exception messages are still your application's responsibility; avoid embedding credentials or secret input in them.

Input validation becomes an ordinary response inside the skeleton's input middleware, so it follows the completion path. Persistent bootstrap failures happen before there is an HTTP request and may have no request ID.

## Outputs and severity

`LogOutput::write(string $record)` accepts a complete encoded record. The web adapter writes through the SAPI error output. You can provide another output implementation through an application-lifetime binding.

Supported levels, from lowest to highest, are debug, info, notice, warning, error, critical, alert and emergency. `JsonLogger` filters before encoding. Its constructor accepts an explicit minimum level:

```php
$logger = new JsonLogger($output, minimum: Level::Warning);
```

For DI, wrap customized construction in a typed static factory and bind that factory, following [Container factories](container.md#static-factories).

JSON logging escapes message content so a newline cannot inject another record. Invalid UTF-8 is replaced during log encoding. Unencodable numeric context fails before a partial record is written.

## Keep lifetimes separate

The output and base `JsonLogger` can be application services. `RequestLogContext`, `RequestLogger` and middleware depending on the request logger are request services. This keeps correlation fresh in native application workers. See [Lifecycle](lifecycle.md#keep-request-state-scoped).

---

[Previous: URL generation](urls.md) | [Next: Database access](database.md)
