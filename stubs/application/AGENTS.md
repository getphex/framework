# Phex application instructions

The official `getphex/phex` repository is an automatically published mirror of
[`stubs/application` in getphex/framework](https://github.com/getphex/framework/tree/main/stubs/application).
Contribute skeleton changes in the framework repository. Applications created from
this template are independent projects and can customize these instructions.

## PHP conventions

Use PHP 8.3-compatible syntax and PHPDoc generics supported by PHPStan and ElephC.
Keep request state separate from application services retained in native workers.
All maintained PHP files must begin with this specific-purpose header, before
`declare`, namespace, imports or declarations:

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

Preserve executable shebangs and declaration PHPDoc, including generic annotations.
See the [Phex contribution conventions](https://github.com/getphex/framework/blob/main/docs/contributing.md)
and [testing guide](https://github.com/getphex/framework/blob/main/docs/testing.md).
