# Configuration

[Documentation](README.md)

Read deployment values through typed settings factories, then inject immutable settings into application services. Builds inspect provider signatures and do not capture the build machine's environment values.

## On this page

- [Configure the skeleton](#configure-the-skeleton)
- [Typed readers](#typed-readers)
- [Settings factories](#settings-factories)
- [Keep build and runtime settings distinct](#keep-build-and-runtime-settings-distinct)
- [Startup failures](#startup-failures)

## Configure the skeleton

The skeleton reads these values at application startup:

| Variable | Default | Meaning |
| --- | --- | --- |
| `PHEX_GREETING` | `world` | Home response greeting |
| `PHEX_PAGE_SIZE` | `20` | Default search limit, from 1 to 100 |
| `PHEX_UPPERCASE_GREETING` | `false` | Uppercase the home greeting |

Inside your application:

```sh
PHEX_GREETING=phex PHEX_PAGE_SIZE=10 PHEX_UPPERCASE_GREETING=true composer serve
```

`GET /` returns `{"hello":"PHEX"}`. `GET /search` uses a limit of ten. There is no automatic `.env` loader in the current skeleton; provide values through your shell, service manager or host configuration.

## Typed readers

`Phex\Config\Environment` wraps an injected `EnvironmentReader`:

```php
$greeting = $environment->nonEmptyString('PHEX_GREETING', 'world');
$pageSize = $environment->integer('PHEX_PAGE_SIZE', 20, min: 1, max: 100);
$uppercase = $environment->boolean('PHEX_UPPERCASE_GREETING', false);
```

| Reader | Accepted values |
| --- | --- |
| `string` | Present strings, including empty strings and `"0"` |
| `nonEmptyString` | A string with at least one byte |
| `integer` | Checked signed decimal integers within optional bounds |
| `boolean` | Exactly `true`, `false`, `1` or `0` |
| `map` | An explicit typed conversion callback |

Defaults apply to missing values. A present but invalid value raises `InvalidConfiguration`; it does not fall back to the default. Bounds also validate default integers. Readers preserve whitespace unless your conversion explicitly removes it.

For a custom conversion, use a typed closure:

```php
$label = $environment->map(
    'APP_LABEL',
    fn (string $value): string => strtoupper($value),
);
```

Variable names start with a letter or underscore and continue with letters, digits or underscores. Invalid names and inverted bounds are programming errors.

## Settings factories

The skeleton already registers `EnvironmentReader`, `Environment` and the application-lifetime `App\Config\ApplicationSettings` factory. Its consumers receive the DTO rather than repeatedly consulting the process environment.

With PHP front controllers or native request lifecycle, settings are created per application invocation. With native application lifecycle, settings are created once per worker. Restart workers to apply changed environment values.

`ProcessEnvironment` uses process-local `getenv($name, true)`. `ArrayEnvironment` supplies an immutable explicit snapshot for unit tests.

## Keep build and runtime settings distinct

`config/build.php`, `config/services.php` and `config/server.php` are source definitions consumed by the build host. They describe providers and native options. A settings factory is executed when the application runs, so the same native executable can use different runtime values:

```sh
PHEX_GREETING=production ./var/build/start --workers 4
```

Credentials placed in source defaults or literal service configuration are still source data. Supply credentials as runtime environment values instead.

For PHP-FPM, expose required variables in the pool configuration, accounting for its environment-clearing settings. See [PHP deployment](deployment.md#runtime-environment).

## Startup failures

Invalid settings identify the variable and expected shape without interpolating its raw value. Request-lifecycle hosts report a generic HTTP `500`. In application lifecycle, invalid application settings fail worker bootstrap before traffic is accepted. See [Native servers](native.md#startup-and-shutdown).

---

[Previous: The service container](container.md) | [Next: Routing](routing.md)
