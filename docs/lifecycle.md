# Request and application lifecycle

[Documentation](README.md)

Phex separates reusable application objects from the objects belonging to one HTTP request. That boundary is shared by PHP and ElephC, even when their host processes have different lifetimes.

## On this page

- [Follow a request](#follow-a-request)
- [Choose the host lifetime](#choose-the-host-lifetime)
- [Keep request state scoped](#keep-request-state-scoped)
- [Own resources](#own-resources)
- [Handle failures](#handle-failures)
- [Buffered responses](#buffered-responses)

## Follow a request

A request travels through:

```text
Runtime request reader
  → immutable Request
  → application container / handler factory
  → fresh RequestScope
  → request services, middleware and router
  → endpoint
  → request-resource cleanup
  → buffered response output
```

The kernel closes the request scope in `finally`. A factory or endpoint failure follows the same cleanup path. Application resources close when the owning application container ends.

## Choose the host lifetime

| Host | Application container | Request scope |
| --- | --- | --- |
| PHP development | Recreated per request | Fresh per request |
| Generated PHP / PHP-FPM | Recreated per request | Fresh per request |
| ElephC `Request` | Recreated per request | Fresh per request |
| ElephC `Application` | Retained per worker | Fresh per request |

PHP-FPM normally reuses worker processes. That process reuse does not retain a Phex container between ordinary front-controller requests. ElephC application lifecycle explicitly retains the container.

With eight native application workers, there are eight independent containers. An in-memory counter or repository is local to its worker. Use external storage when business data must be shared between workers or survive restarts.

## Keep request state scoped

Request bodies, authentication decisions, path captures, correlation context and active transactions belong to request objects or request services. Application services should retain reusable configuration, immutable helpers or deliberately shared resources.

For example, a stateless `Greeter` can have application lifetime. A `Session` that owns one request's database transaction has request lifetime. A shared logger output can have application lifetime while its request-enriched logger has request lifetime.

The [container analyzer](container.md#lifetime-rules) rejects application services that capture request dependencies through their dependency graph. It cannot make mutable state in your own static properties request-local. Keep that state explicit.

## Own resources

A cleanup resource implements `Phex\Contracts\Kernel\ScopeResource::close(): void`. The database [Session](../src/Database/Session.php) and example [RequestTrace](../examples/request-lifecycle/RequestTrace.php) are complete implementations.

Registering a resource as a request service gives the container responsibility for cleanup. When composing the kernel manually, use `$scope->own($resource)`; its generic result retains the concrete resource type.

Scopes deduplicate by object identity and close resources in reverse registration order. Closing is idempotent. Registering a new resource after closure begins is a programming error.

## Handle failures

All registered resources are attempted even if one cleanup call fails. If cleanup succeeds, the original request exception propagates. If cleanup also fails, `CleanupFailed` retains the request failure and the cleanup causes.

The web host returns a generic `500 Internal Server Error` for unexpected failures and reports the error through its runtime adapter. Input validation has a separate, explicit [response policy](json.md#input-errors).

A failed request in application lifecycle does not discard ordinary application state. A database transaction is rolled back by session cleanup; a mutation to an unrelated application-owned array is not automatically undone.

## Buffered responses

Current responses own complete string bodies. Request cleanup finishes before the body is emitted. Streaming responses and connection upgrades need a different completion boundary and are outside the current API.

---

[Previous: Directory structure](structure.md) | [Next: The service container](container.md)
