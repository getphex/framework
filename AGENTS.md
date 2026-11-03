# Phex agent instructions

## Required PHP file header

Every maintained PHP source must begin with this file-level PHPDoc template,
immediately after the PHP opening tag and before `declare`, the namespace,
imports or declarations:

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

- Replace the example description with a concrete explanation of the file's
  purpose. Keep the metadata values and their order exactly as shown.
- Apply this format to framework code, tests, fixtures, examples, application
  skeleton files, the PHP CLI and PHP analysis stubs.
- Preserve executable shebangs before the opening PHP tag.
- Keep existing class, method and property PHPDoc, including generic annotations.
  The file header is separate from those declaration comments.
- PHP generators must emit the same header format. Update their templates so
  rebuilding preserves both the purpose description and the required metadata.

The same template is documented in [Contributing](docs/contributing.md#php-file-headers).

## User documentation

- Keep the English handbook under `docs/` organized by user task and current
  public APIs. Use its index as the navigation entry point.
- Give each guide one topic, explicit prerequisites/working directory, practical
  examples and expected output where appropriate.
- Keep examples consistent with actual imports, signatures, service definitions
  and supported PHP/ElephC behavior. State compiler capability requirements.
- Update the relevant guides when behavior changes and verify local links,
  anchors and copyable examples. Keep internal journal details out of user guides.
- Follow the [documentation contribution guide](docs/contributing.md#maintain-the-documentation).

## Application skeleton distribution

- Maintain the application template only in `stubs/application/`. The
  `getphex/phex` repository is its automatically published mirror.
- Keep framework package `getphex/framework` and application project
  `getphex/phex` distinct. Update installed-package paths and the relevant guides
  when changing their distribution.
- Follow [Package distribution](docs/distribution.md) when editing the split
  workflow or preparing a release.
