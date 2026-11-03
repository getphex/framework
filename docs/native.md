# Native servers

[Documentation](README.md)

ElephC compiles the same shared application sources into a native HTTP executable. Phex chooses the entry point and lifetime through explicit server configuration.

## On this page

- [Compiler requirements](#compiler-requirements)
- [Choose a lifecycle](#choose-a-lifecycle)
- [Build and start](#build-and-start)
- [Startup options](#startup-options)
- [Startup and shutdown](#startup-and-shutdown)
- [Deploy the artifact](#deploy-the-artifact)
- [Quota recycling in the pinned runtime](#quota-recycling-in-the-pinned-runtime)

## Compiler requirements

The default request lifecycle works with release ElephC 0.27.6. Application lifecycle requires a compiler whose help includes `--web-lifecycle`; the compatibility matrix targets the precompiled release SDK 0.27.7 for this mode.
Phex CI downloads official release archives and checks their SHA-256 and required
runtime libraries. It never compiles ElephC from source.

Inspect your SDK:

```sh
elephc --version
elephc --help
elephc --print-capabilities
```

Use the compiler together with its required runtime bridge archives. An executable copied without its SDK archives may type-check code but fail at linking. Database applications need the PDO bridge. The supported capability command is `--print-capabilities`.

Select an SDK for a build without changing the global compiler selection:

```sh
PHEX_ELEPHC=/absolute/path/to/elephc composer build:native
```

## Choose a lifecycle

In **your application's** `config/server.php`, retain its standard PHP header and return:

```php
use Phex\Build\Config\NativeLifecycle;
use Phex\Build\Config\NativeServerConfig;

return new NativeServerConfig(
    lifecycle: NativeLifecycle::Application,
    listen: '127.0.0.1:8080',
    workers: 4,
    maxRequests: 1000,
);
```

`Request` is the default. It creates/closes the application container per request. `Application` retains one container per worker while creating a fresh request scope for every dispatch. See [Lifecycle](lifecycle.md#choose-the-host-lifetime) before retaining mutable services.

Phex checks application-lifecycle capability during compilation and reports an unsupported SDK. The configured application mode uses native worker isolation. Other low-level ElephC isolation modes are separate compiler/server capabilities, not fields in `NativeServerConfig`.

## Build and start

```sh
composer build:native
./var/build/start --listen 127.0.0.1:8080 --workers 1
```

The compile command generates `var/build/server.php`, invokes ElephC and writes `server.lifecycle` only after success. The wrapper includes `native/main.php` for request lifecycle or `native/application.php` for application lifecycle.

The executable serves HTTP directly. A reverse proxy is an optional deployment layer; account for that extra hop when [comparing performance](benchmarks.md).

## Startup options

The launcher applies explicit arguments first, then environment values, then defaults from server configuration:

```sh
PHEX_LISTEN=0.0.0.0:8080 PHEX_WORKERS=8 ./var/build/start
./var/build/start --workers 2 --max-requests 500 -- --access-log
```

Pass additional native-server flags after `--`. Workers must be positive; max requests must be nonnegative. Zero disables quota recycling, so choose it only with an understood memory/lifetime policy for your workload.

Changing lifecycle requires recompilation. Changing only configured listen/worker/quota defaults needs `composer build` and a launcher restart. Environment/argument overrides need only a restart. The launcher refuses a lifecycle mismatch between its generated configuration and the last successful binary.

## Startup and shutdown

Application containers initialize after fork and close during graceful worker shutdown/recycling. Invalid application settings fail bootstrap before traffic is accepted; no request correlation ID exists yet. Normal request failures close request resources and leave the application container alive.

Worker-local memory is independent. Eight workers have eight containers and, for the example database binding, eight physical database connections. Share durable data through external storage rather than relying on an in-memory repository.

## Deploy the artifact

Deploy `server`, `server.lifecycle` and `start` together for launcher startup. The launcher requires a POSIX shell and `dirname`; the executable can also be started directly with its native options. Runtime HTTP execution needs no PHP or Composer.

Keep the platform/architecture and system dependency requirements of the SDK in mind, and supply runtime environment values, database files and other application data separately. A native binary is not a universal cross-platform package.

## Quota recycling in the pinned runtime

The tested `9419f7ac` runtime can briefly refuse or reset connections when quota-limited workers are replaced, including when multiple workers reach their quota together. The database integration probe bounds retries to idempotent reads during this scenario; Phex does not automatically retry application writes or ordinary HTTP requests.

Treat continuity across worker replacement as a runtime limitation when selecting a deployment/recycling policy. It is separate from transaction cleanup, which is covered by the PHP/native application tests.

---

[Previous: Deploy on PHP](deployment.md) | [Next: Testing](testing.md)
