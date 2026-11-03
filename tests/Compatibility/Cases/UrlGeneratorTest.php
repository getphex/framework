<?php

/**
 * Verifies named-route encoding, matcher round trips, parameter failures and shared
 * container integration.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use Fiber;
use InvalidArgumentException;
use LogicException;
use Phex\Http\Message\Request;
use Phex\Kernel\Scope\RequestScope;
use Phex\Routing\Definition\Route;
use Phex\Routing\Matcher\TableMatcher;
use Phex\Routing\Url\TableUrlGenerator;
use Phex\Routing\Url\UrlGenerationFailed;
use Phex\Tests\Compatibility\Fixtures\RoutingEchoEndpoint;
use Phex\Tests\Compatibility\Fixtures\UrlCases;
use Phex\Tests\Generated\EmptyRoutingContainer;
use Phex\Tests\Generated\EmptyRoutingContainerUrlGenerator;
use Phex\Tests\Generated\NamedRoutingContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlGeneratorTest extends TestCase
{
    /** @return list<array{string|int, string}> */
    public static function values(): array
    {
        return [
            [42, '42'], [0, '0'], [-42, '-42'], [PHP_INT_MAX, (string) PHP_INT_MAX],
            ['0007', '0007'], ['a/b', 'a%2Fb'], ['a+b c', 'a%2Bb%20c'],
            ['%2F', '%252F'], ['?#=&', '%3F%23%3D%26'], ['\\name', '%5Cname'],
            ["caf\xc3\xa9", 'caf%C3%A9'], ["\xff", '%FF'], ['-._~AZaz09', '-._~AZaz09'],
            ['//example.test', '%2F%2Fexample.test'], ['.hidden', '.hidden'], ['...', '...'],
        ];
    }

    #[DataProvider('values')]
    public function testCompiledAndTableGeneratorsEncodeOneSegmentAndRoundTrip(string|int $value, string $encoded): void
    {
        $container = new NamedRoutingContainer();
        try {
            $expected = '/files/'.$encoded;
            $path = $container->urls()->generate('files.show', ['name' => $value]);
            $this->assertSame($expected, $path);
            $this->assertSame($expected, UrlCases::table()->generate('files.show', ['name' => $value]));
            $match = $container->matcher()->match(new Request('GET', $path));
            $this->assertSame('files.show', $match->route()->name);
            $this->assertSame((string) $value, $match->parameters->get('name'));
        } finally {
            $container->close();
        }
    }

    public function testLiteralEncodingRootAndRepeatedSlashesArePrecompiledConsistently(): void
    {
        $container = new NamedRoutingContainer();
        $table = UrlCases::table();
        try {
            $this->assertSame('/', $container->urls()->generate('home'));
            $this->assertSame('/', $table->generate('home'));
            $this->assertSame('/literal%2Bpercent%25/caf%C3%A9', $container->urls()->generate('literal'));
            $this->assertSame('/literal%2Bpercent%25/caf%C3%A9', $table->generate('literal'));
            $this->assertSame('/quote%22/dollar%24/back%5Cslash', $container->urls()->generate('quotes'));
            $this->assertSame('/quote%22/dollar%24/back%5Cslash', $table->generate('quotes'));
            $this->assertSame('/a//x%2Fy/', $container->urls()->generate('trailing', ['name' => 'x/y']));
            $this->assertSame('/a//x%2Fy/', $table->generate('trailing', ['name' => 'x/y']));
            $this->assertSame('/upper', $container->urls()->generate('Upper_Case-2'));
        } finally {
            $container->close();
        }
    }

    public function testParameterNamesAreExplicitAndInputOrderDoesNotAffectPathOrder(): void
    {
        $parameters = ['id' => 7, 'team' => 'a/b'];
        $container = new NamedRoutingContainer();
        try {
            $this->assertSame('/teams/a%2Fb/users/7', $container->urls()->generate('teams.user', $parameters));
            $this->assertSame('/teams/a%2Fb/users/7', UrlCases::table()->generate('teams.user', $parameters));
            $this->assertSame('/users/7', $container->urls()->generate('users.show', ['id' => 7]));
            $this->assertSame('/users/7', $container->urls()->generate('users.update', ['id' => 7]));
        } finally {
            $container->close();
        }
    }

    /** @return list<array{string}> */
    public static function invalidValues(): array
    {
        return [[''], ['.'], ['..'], ["a\0b"], ["a\nb"], ["a\x7fb"]];
    }

    #[DataProvider('invalidValues')]
    public function testCompiledAndTableGeneratorsRejectInvalidValues(string $value): void
    {
        $container = new NamedRoutingContainer();
        $errors = 0;
        try {
            try {
                $container->urls()->generate('files.show', ['name' => $value]);
            } catch (UrlGenerationFailed) {
                $errors++;
            }
            try {
                UrlCases::table()->generate('files.show', ['name' => $value]);
            } catch (UrlGenerationFailed) {
                $errors++;
            }
            $this->assertSame(2, $errors);
        } finally {
            $container->close();
        }
    }

    /** @return list<array{string, string}> */
    public static function invalidCalls(): array
    {
        return [['unknown', 'unknown'], ['unnamed', 'unknown'], ['Home', 'unknown'], ['files.show', 'missing'], ['home', 'extra']];
    }

    #[DataProvider('invalidCalls')]
    public function testUnknownMissingAndExtraParametersHaveTheSameFailures(string $name, string $kind): void
    {
        $container = new NamedRoutingContainer();
        $parameters = [];
        if ($kind === 'extra') {
            $parameters['extra'] = 'value';
        }
        $errors = [];
        try {
            try {
                $container->urls()->generate($name, $parameters);
            } catch (UrlGenerationFailed $error) {
                $errors[] = $error->getMessage();
            }
            try {
                UrlCases::table()->generate($name, $parameters);
            } catch (UrlGenerationFailed $error) {
                $errors[] = $error->getMessage();
            }
            $this->assertCount(2, $errors);
            $this->assertSame($errors[0], $errors[1]);
        } finally {
            $container->close();
        }
    }

    public function testNamesAreOptionalAndCaseSensitive(): void
    {
        $endpoint = new RoutingEchoEndpoint();
        $route = new Route('GET', '/plain', $endpoint);
        $this->assertNull($route->name);
        $this->assertNull((new TableMatcher([$route]))->match(new Request('GET', '/plain'))->route()->name);
        $table = new TableUrlGenerator([
            new Route('GET', '/one', $endpoint, name: 'home'),
            new Route('GET', '/two', $endpoint, name: 'Home'),
        ]);
        $this->assertSame('/one', $table->generate('home'));
        $this->assertSame('/two', $table->generate('Home'));
    }

    /** @return list<array{string}> */
    public static function invalidNames(): array
    {
        return [[''], ['1route'], ['two words'], ['a/b'], ['a?b'], ['a#b'], ['.route'], ['route"'], [str_repeat('a', 129)]];
    }

    #[DataProvider('invalidNames')]
    public function testNamesAreValidatedWhenRoutesAreDefined(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Route('GET', '/', new RoutingEchoEndpoint(), name: $name);
    }

    public function testNamesAtTheMaximumLengthAreAccepted(): void
    {
        $route = new Route('GET', '/', new RoutingEchoEndpoint(), name: str_repeat('a', 128));
        $this->assertSame('/', (new TableUrlGenerator([$route]))->generate(str_repeat('a', 128)));
    }

    /** @return list<array{string}> */
    public static function unsafePatterns(): array
    {
        return [['//example.test/path'], ['///path'], ['/a/../b'], ['/./path']];
    }

    #[DataProvider('unsafePatterns')]
    public function testNamedPathsCannotBecomeAuthorityReferencesOrNormalizeDotSegments(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Route('GET', $path, new RoutingEchoEndpoint(), name: 'unsafe');
    }

    public function testUnnamedPatternMatchingKeepsItsExistingDotAndSlashSemantics(): void
    {
        $matcher = new TableMatcher([new Route('GET', '//a/../b', new RoutingEchoEndpoint())]);
        $this->assertSame('//a/../b', $matcher->match(new Request('GET', '//a/../b'))->route()->path);
    }

    public function testDuplicateNamesAreRejectedAcrossMethodsByTheTableGenerator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TableUrlGenerator([
            new Route('GET', '/one', new RoutingEchoEndpoint(), name: 'same'),
            new Route('POST', '/two', new RoutingEchoEndpoint(), name: 'same'),
        ]);
    }

    public function testDuplicateNamesAreRejectedByTheMatcherAsWell(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TableMatcher([
            new Route('GET', '/one', new RoutingEchoEndpoint(), name: 'same'),
            new Route('POST', '/two', new RoutingEchoEndpoint(), name: 'same'),
        ]);
    }

    public function testEmptyGeneratedAndTableRegistriesReportUnknownNames(): void
    {
        $container = new EmptyRoutingContainer();
        $errors = 0;
        try {
            try {
                (new EmptyRoutingContainerUrlGenerator())->generate('missing');
            } catch (UrlGenerationFailed) {
                $errors++;
            }
            try {
                (new TableUrlGenerator([]))->generate('missing');
            } catch (UrlGenerationFailed) {
                $errors++;
            }
            $this->assertSame(2, $errors);
        } finally {
            $container->close();
        }
    }

    public function testTheApplicationAndRequestContextShareTheInjectedGenerator(): void
    {
        $container = new NamedRoutingContainer();
        $scope = new RequestScope(new Request('GET', '/'));
        try {
            $this->assertSame($container->urls(), $container->links()->urls);
            $this->assertSame($container->urls(), $container->request($scope)->links()->urls);
            $this->assertSame('/users/42', $container->links()->urls->generate('users.show', ['id' => 42]));
        } finally {
            $scope->close();
            $container->close();
        }
    }

    public function testGeneratorAccessThroughAClosedContainerIsRejected(): void
    {
        $container = new NamedRoutingContainer();
        $container->close();
        $this->expectException(LogicException::class);
        $container->urls();
    }

    public function testInterleavedCallsKeepTheirOwnParameters(): void
    {
        $container = new NamedRoutingContainer();
        $urls = $container->urls();
        $first = new Fiber(function () use ($urls): string {
            $path = $urls->generate('files.show', ['name' => 'alice/a']);
            Fiber::suspend();

            return $path;
        });
        $second = new Fiber(fn (): string => $urls->generate('files.show', ['name' => 'bob+b']));
        try {
            $first->start();
            $second->start();
            $first->resume();
            $this->assertSame('/files/alice%2Fa', $first->getReturn());
            $this->assertSame('/files/bob%2Bb', $second->getReturn());
        } finally {
            $container->close();
        }
    }
}
