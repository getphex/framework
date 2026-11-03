# Testing

[Documentation](README.md)

Use ordinary PHP tests for application behavior, then run portable cases through ElephC when native execution is a target. Framework compatibility checks additionally compare PHP/native JUnit identities and outcomes.

## On this page

- [Test an application action](#test-an-application-action)
- [Analyze application code](#analyze-application-code)
- [Framework checks](#framework-checks)
- [HTTP and database verification](#http-and-database-verification)

## Test an application action

Inside **your application**, add PHPUnit and a test namespace:

```sh
composer require --dev phpunit/phpunit:^11.5
```

Merge this top-level key into `composer.json`, keeping the existing application autoload and other keys:

```json
{"autoload-dev":{"psr-4":{"Tests\\":"tests/"}}}
```

Run `composer dump-autoload`, then create `tests/GreetActionTest.php`:

```php
<?php

/**
 * Verifies the greeting action with explicit request input.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Tests;

use App\Http\Action\GreetAction;
use App\Input\GreetingDecoder;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Routing\Parameter\Parameters;
use PHPUnit\Framework\TestCase;

final class GreetActionTest extends TestCase
{
    public function testItGreetsTheDecodedName(): void
    {
        $action = new GreetAction(new GreetingDecoder());
        $request = new Request('POST', '/greet', new Headers([
            'Content-Type' => ['application/json'],
        ]), '{"name":"Ada"}');

        $response = $action->handle($request, new Parameters());

        $this->assertSame(200, $response->status);
        $this->assertSame('{"hello":"Ada"}', $response->body);
    }
}
```

Execute it with:

```sh
vendor/bin/phpunit --bootstrap vendor/autoload.php tests
```

The example exercises the skeleton's actual action and decoder without starting HTTP. Validation-error conversion belongs to middleware/host tests, so direct action tests can also deliberately assert thrown input exceptions.

For portable cases, with ElephC installed:

```sh
elephc test tests --strict-php
```

Keep assertions and PHP syntax within the test features supported by the selected SDK. The framework's own shared suite is a useful reference for portable test patterns.

## Analyze application code

Keep PHPStan tooling in a separate Composer project, following the framework's isolation approach:

```sh
mkdir -p tools/phpstan
composer --working-dir=tools/phpstan require --dev phpstan/phpstan:^2.1
composer build
```

A starting `phpstan.neon` for the application is:

```yaml
parameters:
    level: max
    phpVersion: 80300
    paths: [app, config, routes, native, var/generated]
    bootstrapFiles: [vendor/autoload.php]
    scanFiles: [vendor/getphex/phex/src/Runtime/Elephc/analysis.stub]
    tmpDir: var/cache/phpstan
```

Run `tools/phpstan/vendor/bin/phpstan analyse --configuration=phpstan.neon`. The analysis-only stub describes compiler-provided worker APIs and must remain outside runtime includes. Isolating PHPStan also keeps its PHAR bootstrap outside the application's native test input graph.

## Framework checks

From the **framework repository root**, after the [development setup](contributing.md#development-setup):

| Command | Coverage |
| --- | --- |
| `composer analyse` | Generate fixtures and run PHPStan at maximum level |
| `composer test:php` | PHP build and shared behavior cases |
| `composer test:elephc` | Native shared behavior cases |
| `composer check` | Analysis, build tests, benchmark-tool tests, installed HTTP/lifecycle/database checks and shared compatibility |
| `composer test:persistent` | Retained native host, shutdown, worker recycling and stripped deployment |

`composer check` requires both PHP and ElephC. It compares shared case identities and outcomes and fails on missing, failed or skipped cases. It also compiles the standalone examples and compares their output with PHP.

Persistent-host checks require application-lifecycle capability. Set `PHEX_ELEPHC` to choose that SDK. The general compatibility command uses `elephc` from `PATH`, so select the same SDK on `PATH` when running the entire command with it.

## HTTP and database verification

Installed-project checks exercise development edits, generated PHP, native HTTP, relocation and distributions stripped of Composer/build/development sources. They verify actual socket status, headers and body behavior.

`composer test:database` tests the SQLite CRUD example, request rollback after unfinished or failed work and physical connection lifetime. On capable SDKs it includes application workers and quota recycling. `python3 -B tools/checks/database_smoke.py --php-only` runs its PHP-only path.

These commands belong to the framework checkout. They are not predefined scripts in a newly generated application's Composer manifest.

---

[Previous: Native servers](native.md) | [Next: CLI reference](cli.md)
