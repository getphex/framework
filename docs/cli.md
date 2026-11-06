# CLI reference

[Documentation](README.md)

The installed `phex` command creates applications, builds generated PHP and compiles native HTTP servers. A generated application's Composer scripts call that installed CLI.

## On this page

- [Framework checkout commands](#framework-checkout-commands)
- [Application commands](#application-commands)
- [Compiler selection](#compiler-selection)
- [Runtime launch options](#runtime-launch-options)
- [Exit failures](#exit-failures)

## Framework checkout commands

Run from the framework repository root:

```sh
php bin/phex --help
php bin/phex new ../my-app --local
```

`--local` adds an explicit Composer path repository pointing at this checkout, with mirroring rather than a runtime symlink. Without that option the template requires the package through its ordinary dependency declaration; a matching package version must be available through configured repositories.

Creation refuses an existing destination. After creating a project, run `composer install` inside it.

## Application commands

Run inside a created application:

| Command | Effect |
| --- | --- |
| `composer serve` | PHP development server on `127.0.0.1:3130`, no build required |
| `composer build` | Generate container, bootstraps and launcher |
| `composer build:native` | Generate and compile the selected native lifecycle |
| `composer serve:native` | Start the previously built native launcher on `127.0.0.1:3131` by default |

The underlying installed commands are:

```sh
vendor/bin/phex build config/build.php
vendor/bin/phex compile config/build.php
```

`build` accepts an `ApplicationConfig` or the lower-level `BuildConfig` for a container-only artifact. `compile` requires `ApplicationConfig`. Configuration files are trusted PHP loaded by the build host.

## Compiler selection

```sh
PHEX_ELEPHC=/absolute/path/to/elephc composer build:native
```

This selects the compiler executable for the native build. `composer build` needs PHP and Composer but does not need an installed compiler.

For framework compatibility scripts, `elephc` is also used through `PATH`. `PHEX_ELEPHC` is not a universal replacement for every tool's own compiler-selection mechanism; check each command's documented option.

## Runtime launch options

The generated `var/build/start` supports `--listen`, `--workers` and `--max-requests`, with both separated and `=` forms. It also reads `PHEX_LISTEN`, `PHEX_WORKERS` and `PHEX_MAX_REQUESTS`. Explicit arguments override environment values and configured defaults.

```sh
./var/build/start --workers=2 --max-requests 500
```

Additional native options can follow `--`. See [Native servers](native.md#startup-options) for lifecycle changes and artifact consistency.

## Exit failures

Invalid arguments, configuration errors, failed graph validation and failed compilation return a nonzero exit status with a diagnostic on stderr. Use that exit status in build automation. The native compiler removes stale executable success artifacts when compilation fails; application build validation preserves previous valid generated application output.

---

[Previous: Testing](testing.md) | [Next: Troubleshooting](troubleshooting.md)
