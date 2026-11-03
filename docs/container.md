# The service container

[Documentation](README.md)

Phex constructs services from explicit definitions. PHP development interprets a validated graph; generated PHP and native programs execute fixed constructor and factory calls.

## On this page

- [Constructor bindings](#constructor-bindings)
- [Static factories](#static-factories)
- [Lifetime rules](#lifetime-rules)
- [Built-in dependencies](#built-in-dependencies)
- [Validation errors](#validation-errors)
- [Generated and development resolution](#generated-and-development-resolution)

## Constructor bindings

Add services to the list in `config/services.php`:

```php
use App\Support\Greeter;
use App\Http\Action\HelloAction;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;

Service::constructor(Greeter::class, Lifetime::Application),
Service::constructor(HelloAction::class, Lifetime::Transient),
```

These are entries inside the existing `new Container([...])` array, not standalone statements to append after its `return`.

For an interface, pass its implementation as the third argument:

```php
Service::constructor(
    EnvironmentReader::class,
    Lifetime::Application,
    ProcessEnvironment::class,
),
```

Import `EnvironmentReader` from `Phex\Contracts\Config` and `ProcessEnvironment` from `Phex\Runtime\Environment` when adding that binding. The skeleton already includes it.

## Static factories

A factory is a public static method with explicit parameter and result types:

```php
Service::factory(
    ApplicationSettings::class,
    Lifetime::Application,
    SettingsFactory::class,
    'create',
),
```

The skeleton's `App\Config\SettingsFactory::create(Environment $environment): ApplicationSettings` is a complete example. Analysis inspects the signature; it does not run the method during a build. The runtime container supplies its dependencies and invokes it.

Factories are useful for reading validated configuration or constructing objects whose constructor arguments should not become service dependencies. The logging setup uses `JsonLogger::create` to keep its enum default inside the factory.

## Lifetime rules

| Lifetime | Reuse | Cleanup owner |
| --- | --- | --- |
| `Application` | One instance per application container | Application container |
| `Request` | One instance per request context | Request scope |
| `Transient` | A new instance for each resolution | The scope that resolves it |

An application service must not depend directly or indirectly on a request service, the current request or a request-only resolver. A transient dependency created for an application service inherits application ownership, so it must also be safe to retain.

Application services are initialized eagerly. Request services are resolved within the request context. An object implementing `ScopeResource` is registered with its effective owner, including resources created before a later constructor fails.

## Built-in dependencies

The container supplies these reserved dependencies where appropriate:

| Type | Availability |
| --- | --- |
| `Phex\Http\Message\Request` | Current request |
| `Phex\Kernel\Scope\RequestScope` | Current request |
| `Phex\Contracts\Routing\EndpointResolver` | Current request |
| `Phex\Contracts\Routing\Matcher` | Nonempty configured route table |
| `Phex\Contracts\Routing\UrlGenerator` | Nonempty configured route table |

Inject them through constructors or factory parameters. User definitions cannot replace reserved dependencies. The built-in matcher and URL generator require at least one configured route. An empty table cannot supply these container dependencies.

## Validation errors

Both backends reject missing services, dependency cycles, invalid provider result types, unsupported variadic/by-reference/union arguments and unsafe lifetime captures. Required scalar constructor arguments need an explicit factory; supported scalar defaults can be emitted directly.

Fix the definition or provider signature. A failed build preserves the previous valid generated application artifacts. [Troubleshooting](troubleshooting.md#container-build-failures) lists common causes.

## Generated and development resolution

Prefer typed constructor injection in application code. Generated public entries, when configured in the container definition, are typed methods rather than a reflective runtime service locator.

For development-only composition, `DevelopmentContainer::get(Foo::class)` has a generic result. Request and transient services are obtained from the request context associated with a `RequestScope`. This interpreted API is kept outside native compilation.

See [Builds](builds.md) for regeneration rules and [Lifecycle](lifecycle.md) for ownership during failure and shutdown.

---

[Previous: Request and application lifecycle](lifecycle.md) | [Next: Configuration](configuration.md)
