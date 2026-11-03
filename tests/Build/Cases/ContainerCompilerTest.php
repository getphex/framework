<?php

/**
 * Verifies dependency validation, deterministic PHP emission and atomic compiled-container
 * artifact replacement.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Cases;

use Phex\Build\Analysis\InvalidContainer;
use Phex\Build\Compiler\BuildConfig;
use Phex\Build\Compiler\ContainerCompiler;
use Phex\Build\Emitter\Literal;
use Phex\Container\Definition\Container;
use Phex\Container\Definition\EndpointRoute;
use Phex\Container\Definition\Lifetime;
use Phex\Container\Definition\Service;
use Phex\Examples\CompiledContainer\Factories;
use Phex\Examples\CompiledContainer\RequestSession;
use Phex\Examples\CompiledContainer\UserEndpoint;
use Phex\Examples\JsonApi\HealthEndpoint;
use Phex\Http\Message\Request;
use Phex\Tests\Build\Fixtures\BadFactories;
use Phex\Tests\Build\Fixtures\Bridge;
use Phex\Tests\Build\Fixtures\CaptiveRoot;
use Phex\Tests\Build\Fixtures\CycleA;
use Phex\Tests\Build\Fixtures\CycleB;
use Phex\Tests\Build\Fixtures\Defaults;
use Phex\Tests\Build\Fixtures\Missing;
use Phex\Tests\Build\Fixtures\NeedsMissing;
use Phex\Tests\Build\Fixtures\NeedsRequest;
use Phex\Tests\Build\Fixtures\ReferenceDependency;
use Phex\Tests\Build\Fixtures\ScalarDependency;
use Phex\Tests\Build\Fixtures\UnionDependency;
use Phex\Tests\Build\Fixtures\VariadicDependency;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContainerCompilerTest extends TestCase
{
    /** @return list<array{Container, string}> */
    public static function invalidGraphs(): array
    {
        return [
            [new Container([Service::constructor(NeedsMissing::class, Lifetime::Request)]), 'Missing binding:'],
            [new Container([Service::constructor(Missing::class, Lifetime::Transient), Service::constructor(Missing::class, Lifetime::Application)]), 'Duplicate or reserved'],
            [new Container([Service::constructor(Request::class, Lifetime::Request)]), 'Duplicate or reserved'],
            [new Container([Service::constructor(CycleA::class, Lifetime::Request), Service::constructor(CycleB::class, Lifetime::Request)]), 'Dependency cycle:'],
            [new Container([Service::constructor(NeedsRequest::class, Lifetime::Application)]), 'captures request state:'],
            [new Container([
                Service::constructor(CaptiveRoot::class, Lifetime::Application),
                Service::constructor(Bridge::class, Lifetime::Transient),
                Service::factory(RequestSession::class, Lifetime::Request, Factories::class, 'session'),
            ]), CaptiveRoot::class.' -> '.Bridge::class.' -> '.RequestSession::class],
            [new Container([Service::constructor(ScalarDependency::class, Lifetime::Request)]), 'literal default'],
            [new Container([Service::constructor(UnionDependency::class, Lifetime::Request)]), 'named object type'],
            [new Container([Service::constructor(VariadicDependency::class, Lifetime::Request)]), 'Variadic or by-reference'],
            [new Container([Service::constructor(ReferenceDependency::class, Lifetime::Request)]), 'Variadic or by-reference'],
            [new Container([], ['missing' => Missing::class]), 'Missing entry binding:'],
            [new Container([Service::constructor(Missing::class, Lifetime::Request)], ['create' => Missing::class]), 'reserved entry name'],
            [new Container([Service::constructor(Missing::class, Lifetime::Request)], ['thing' => Missing::class, 'Thing' => Missing::class]), 'reserved entry name'],
            [new Container([], routes: [new EndpointRoute('GET', '/users', UserEndpoint::class)]), 'Missing endpoint binding:'],
            [new Container([Service::constructor(HealthEndpoint::class, Lifetime::Transient)], routes: [new EndpointRoute('GET', '/{id}/{id}', HealthEndpoint::class)]), 'Invalid compiled route:'],
            [new Container([Service::constructor(HealthEndpoint::class, Lifetime::Transient)], routes: [new EndpointRoute('GET', '/{id}', HealthEndpoint::class), new EndpointRoute('GET', '/{name}', HealthEndpoint::class)]), 'Invalid compiled routes:'],
        ];
    }

    #[DataProvider('invalidGraphs')]
    public function testInvalidGraphsFailBeforeEmission(Container $definition, string $message): void
    {
        $this->expectException(InvalidContainer::class);
        $this->expectExceptionMessage($message);
        (new ContainerCompiler())->compile($definition, 'Test\\Generated');
    }

    /** @return list<array{string}> */
    public static function invalidFactories(): array
    {
        return [['nullable'], ['hidden'], ['instance'], ['scalar'], ['wrong'], ['absent']];
    }

    #[DataProvider('invalidFactories')]
    public function testFactoriesAreValidated(string $method): void
    {
        $this->expectException(InvalidContainer::class);
        (new ContainerCompiler())->compile(new Container([
            Service::factory(Missing::class, Lifetime::Request, BadFactories::class, $method),
        ]), 'Test\\Generated');
    }

    public function testEmissionIsDeterministicAndUsesDirectCalls(): void
    {
        $first = Service::constructor(Defaults::class, Lifetime::Application);
        $second = Service::constructor(Missing::class, Lifetime::Request);
        $compiler = new ContainerCompiler();
        $a = $compiler->compile(new Container([$first, $second], ['defaults' => Defaults::class, 'missing' => Missing::class]), 'Test\\Generated');
        $b = $compiler->compile(new Container([$second, $first], ['missing' => Missing::class, 'defaults' => Defaults::class]), 'Test\\Generated');
        $this->assertSame($a, $b);
        $this->assertStringContainsString('new \\'.Defaults::class.'(', $a);
        $this->assertStringContainsString('function defaults(): \\'.Defaults::class, $a);
        $this->assertStringNotContainsString('Reflection', $a);
        $this->assertStringNotContainsString('eval(', $a);
        $this->assertStringNotContainsString('new $', $a);
        $this->assertStringContainsString('\\PHP_INT_MIN', $a);
    }

    public function testLiteralsEscapeInterpolationAndBinaryBytes(): void
    {
        $this->assertSame('"'.'\$x'.'\x0A'.'"', Literal::emit("\$x\n"));
        $this->assertSame('"'.'\x00'.'\xFF'.'\"'.'\\\\'.'"', Literal::emit("\x00\xFF\"\\"));
        $this->assertSame('["key" => [0 => true, 1 => null, 2 => 1.25]]', Literal::emit(['key' => [true, null, 1.25]]));
        $this->expectException(InvalidContainer::class);
        Literal::emit(new Missing());
    }

    public function testWritingIsIdempotentAndValidationPreservesThePreviousArtifact(): void
    {
        $directory = dirname(__DIR__, 3).'/var/compiler-test-'.bin2hex(random_bytes(8));
        $output = $directory.'/Generated.php';
        $compiler = new ContainerCompiler();
        $config = new BuildConfig(new Container([Service::constructor(Missing::class, Lifetime::Application)]), 'Test\\Generated', $output);
        try {
            $this->assertTrue($compiler->build($config));
            $this->assertFalse($compiler->build($config));
            $original = file_get_contents($output);
            try {
                $compiler->build(new BuildConfig(new Container([Service::constructor(NeedsMissing::class, Lifetime::Request)]), 'Test\\Generated', $output));
                $this->fail('Invalid graph must not overwrite the artifact.');
            } catch (InvalidContainer) {
                $this->assertSame($original, file_get_contents($output));
            }
            $this->assertSame(['.', '..', 'Generated.php'], scandir($directory));
        } finally {
            if (is_file($output)) {
                unlink($output);
            }
            if (is_dir($directory)) {
                rmdir($directory);
            }
        }
    }
}
