# Contributing to Phex

[Documentation](README.md)

Changes should preserve one shared application API across PHP and ElephC, explicit ownership and a practical PHP development workflow.

## On this page

- [Development setup](#development-setup)
- [Shared source rules](#shared-source-rules)
- [PHP file headers](#php-file-headers)
- [Maintain the documentation](#maintain-the-documentation)
- [Compatibility changes](#compatibility-changes)
- [License](#license)

## Development setup

From the framework checkout:

```sh
composer install
composer tools:install
composer check
```

Framework development needs `pdo_sqlite`, Python 3.10+ and a complete ElephC SDK as well as PHP/Composer. Native builds need platform build tools and `libbz2-dev` on Debian/Ubuntu. To exercise application lifetime, select a capable SDK through `PHEX_ELEPHC` and run `composer test:persistent`.

See [Testing](testing.md#framework-checks) for narrower commands. Run checks appropriate to the change before submitting it.

## Shared source rules

Shared framework and application source must execute directly on supported PHP. Use supported PHPDoc generics with ordinary PHP signatures, including typed callable literals at generic mapping boundaries.

Keep dependency resolution statically analyzable, routing precompilable and middleware typed. Build-host analysis may inspect declarations. Request handling must avoid `eval`, dynamic class generation and per-request reflective dependency discovery on the compiled path.

Keep application state and request state separate. Native-only worker registration belongs in its dedicated entry graph. Development reflection, build tooling, analysis stubs and native worker entries each have explicit loading boundaries.

PHPStan runs at `level: max` across maintained PHP sources and generated artifacts. Fix type errors at their boundary; do not hide them with a broad ignored-error list or baseline.

## PHP file headers

Every maintained PHP source begins with this file-level PHPDoc, after `<?php` and before `declare`, namespace, imports or declarations:

```php
<?php

/**
 * Explains the specific purpose of this file.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);
```

Replace the description with the file's concrete purpose. Keep metadata values and order. Preserve executable shebangs before the PHP opening tag and keep class/method/property PHPDoc, including generics, in their original declaration positions.

This includes tests, fixtures, examples, the skeleton, the CLI and analysis stubs. PHP emitters must produce the same header in generated files. Update templates when changing generated output rather than patching artifacts manually.

## Maintain the documentation

The handbook is organized around user tasks and current public APIs. Each page has one topic, a short introduction, a contents list and links to related or adjacent pages. Add new guides to the [documentation index](README.md).

Examples should state their working directory and use actual namespace imports, signatures and explicit service registrations. Show expected output for runnable commands. Keep complete-file examples consistent with the PHP header convention; label excerpts that belong inside an existing definition or method.

Update guides with behavior changes. State compiler capability requirements and tested limitations where they affect a user's choice. Keep machine-specific benchmark results, internal task IDs and agent runbooks outside the user guide.

## Compatibility changes

Add meaningful shared behavior cases when changing runtime behavior. Build analysis/emission belongs in PHP host tests; request APIs and ownership rules belong in PHP/native compatibility cases. Test installed/native graphs when crossing bootstrap or source-discovery boundaries.

Avoid publishing performance claims from a single unmatched request path. [Benchmarking](benchmarks.md) explains the transport and environment controls used by this repository.

## License

Phex is distributed under the [MIT License](../LICENSE), with the copyright attribution in its file headers and license notice. Preserve the notice when distributing project code.

The [distribution guide](distribution.md) describes the canonical skeleton,
automatic publication and package release boundary.

---

[Previous: Troubleshooting](troubleshooting.md) | [Next: Package distribution](distribution.md)
