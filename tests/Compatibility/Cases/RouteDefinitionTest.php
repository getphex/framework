<?php

/**
 * Verifies route pattern validation and rejection of structurally ambiguous route
 * definitions.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use InvalidArgumentException;
use Phex\Routing\Definition\PathPattern;
use Phex\Routing\Definition\Route;
use Phex\Routing\Matcher\TableMatcher;
use Phex\Tests\Compatibility\Fixtures\TextEndpoint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RouteDefinitionTest extends TestCase
{
    public function testDefinitionsPreserveMethodPathAndEndpoint(): void
    {
        $endpoint = new TextEndpoint('ok');
        $route = new Route('custom-METHOD', '/users/{user_id42}', $endpoint);

        $this->assertSame('custom-METHOD', $route->method);
        $this->assertSame('/users/{user_id42}', $route->path);
        $this->assertSame($endpoint, $route->endpoint);
        $this->assertSame('/users/{user_id42}', $route->pattern->path);
    }

    /** @return list<array{string}> */
    public static function invalidPatterns(): array
    {
        return [
            [''], ['relative'], ['/?query'], ['/#fragment'], ['/{id}/{id}'],
            ['/{42}'], ['/{}'], ['/prefix{id}'], ['/{id}suffix'], ['/{foo-bar}'],
            ['/{'], ['/}'], ["/bad\0"], ["/bad\x7f"],
        ];
    }

    #[DataProvider('invalidPatterns')]
    public function testInvalidPatternsFailDuringDefinition(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PathPattern($path);
    }

    public function testInvalidMethodsFailDuringDefinition(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Route('BAD METHOD', '/', new TextEndpoint('unused'));
    }

    public function testDuplicateStaticRoutesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TableMatcher([
            new Route('GET', '/health', new TextEndpoint('first')),
            new Route('GET', '/health', new TextEndpoint('second')),
        ]);
    }

    public function testRenamingAPlaceholderDoesNotResolveAnAmbiguity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TableMatcher([
            new Route('GET', '/users/{id}', new TextEndpoint('first')),
            new Route('GET', '/users/{name}', new TextEndpoint('second')),
        ]);
    }
}
