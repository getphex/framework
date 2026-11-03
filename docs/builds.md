# Builds and development

[Documentation](README.md)

Phex has a build-free PHP development path and explicit generated PHP/native paths. Choose the path first, then rebuild only the artifacts that path uses.

## On this page

- [Development PHP](#development-php)
- [Generate PHP artifacts](#generate-php-artifacts)
- [Compile native output](#compile-native-output)
- [Know when to rebuild](#know-when-to-rebuild)
- [Relocate and deploy](#relocate-and-deploy)

## Development PHP

Inside a created application:

```sh
composer serve
```

`public/index.php` interprets `config/services.php` and `routes/web.php`. New application classes and definition changes are loaded on subsequent requests. No generated artifact is required.

When OPcache is enabled for development, enable timestamp validation and a revalidation interval that reflects edits promptly. A persistent PHP host would also need to reload its process for changed class declarations; such a host adapter is not part of the current skeleton.

## Generate PHP artifacts

```sh
composer build
```

`config/build.php` returns an `ApplicationConfig`. The build validates service signatures, lifetimes and routes, then discovers application declarations under the configured PSR-4 source roots.

| Artifact | Purpose |
| --- | --- |
| `var/generated/ApplicationContainer.php` | Fixed service resolution, request container, matcher and named URL generator |
| `var/generated/php-bootstrap.php` | Explicit declarations for PHP SAPI |
| `var/generated/native-bootstrap.php` | Explicit declarations for native request lifecycle |
| `var/generated/native-application-bootstrap.php` | Explicit declarations for retained native application lifetime |
| `var/build/start` | Native launcher with configured startup defaults |

Generated files have purpose and attribution headers. Output is validated before replacement, written atomically and retained when unchanged.

## Compile native output

```sh
composer build:native
```

This generates artifacts and invokes ElephC with `--web --strict-php`, selecting the lifecycle entry from server configuration. Application lifecycle adds its worker/application flags. A successful compile writes the executable and lifecycle marker; failure removes stale native executable/intermediate/success-marker output.

Build tooling and PHP development reflection stay outside the native runtime graph. Application types must remain ordinary executable PHP with supported PHPDoc generics. Keep native worker registration outside shared source roots.

## Know when to rebuild

| Change | PHP development | Generated PHP | Native executable |
| --- | --- | --- | --- |
| Method body in an already included file | Next request | Next request, subject to OPcache | Recompile |
| New type/file, routes, bindings, provider signature or declaration dependencies | Next request | `composer build` | `composer build:native` |
| Runtime environment values | New application invocation | New application invocation | Restart with new environment |
| Native lifecycle | No effect on PHP host | No effect on PHP host | Recompile |
| Native startup overrides | Not applicable | Not applicable | Restart launcher with new options/environment |

A body change that introduces a new declaration dependency belongs in the structural-change row. Changing literal source defaults is also a source change. See [Native servers](native.md#startup-options) for configured defaults versus startup overrides.

## Relocate and deploy

Generated includes use relative paths. Keep their directory relationships when deploying generated PHP. Native execution embeds the compiled declaration graph and runs without runtime PHP/Composer; deploy runtime data and required platform dependencies separately.

Do not edit generated classes to add bindings. Change source definitions, regenerate and deploy a consistent set of artifacts. [Deployment](deployment.md) and [Native servers](native.md) describe the target-specific files.

---

[Previous: Run the SQLite CRUD example](database-tutorial.md) | [Next: Deploy on PHP](deployment.md)
