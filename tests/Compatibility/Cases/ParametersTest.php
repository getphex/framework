<?php

/**
 * Verifies immutable route captures, checked integer conversion and generic parameter
 * mapping.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use OutOfBoundsException;
use Phex\Routing\Parameter\InvalidParameter;
use Phex\Routing\Parameter\Parameters;
use Phex\Tests\Compatibility\Fixtures\UserId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ParametersTest extends TestCase
{
    public function testParametersAreIndependentImmutableValues(): void
    {
        $input = ['name' => 'alice', 'empty' => ''];
        $parameters = new Parameters($input);
        $input['name'] = 'bob';
        $exported = $parameters->all();
        $exported['name'] = 'changed';

        $this->assertTrue($parameters->has('name'));
        $this->assertTrue($parameters->has('empty'));
        $this->assertFalse($parameters->has('missing'));
        $this->assertSame('alice', $parameters->get('name'));
        $this->assertSame('', $parameters->get('empty'));
    }

    public function testMissingParametersRaiseAProgrammingError(): void
    {
        $parameters = new Parameters();
        $this->expectException(OutOfBoundsException::class);
        $parameters->get('missing');
    }

    /** @return list<array{string, int}> */
    public static function integers(): array
    {
        return [
            ['0', 0], ['-0', 0], ['000', 0], ['42', 42], ['0042', 42],
            ['-42', -42], ['-0042', -42],
            [(string) PHP_INT_MAX, PHP_INT_MAX], [(string) PHP_INT_MIN, PHP_INT_MIN],
        ];
    }

    #[DataProvider('integers')]
    public function testIntegersAreConvertedExplicitly(string $raw, int $expected): void
    {
        $parameters = new Parameters(['id' => $raw]);

        $this->assertSame($expected, $parameters->integer('id'));
        $this->assertSame($raw, $parameters->get('id'));
    }

    /** @return list<array{string}> */
    public static function invalidIntegers(): array
    {
        return [
            [''], ['-'], ['+42'], [' 42'], ['42 '], ['4.2'], ['4e2'], ['0x2a'],
            ['42x'], ["4\0"], [(string) PHP_INT_MAX.'0'], [(string) PHP_INT_MIN.'0'],
        ];
    }

    #[DataProvider('invalidIntegers')]
    public function testInvalidAndOverflowingIntegersAreRejected(string $raw): void
    {
        $parameters = new Parameters(['id' => $raw]);
        $this->expectException(InvalidParameter::class);
        $parameters->integer('id');
    }

    public function testGenericConvertersPreserveTheirResultTypes(): void
    {
        $parameters = new Parameters(['name' => 'alice', 'id' => '42']);
        $name = $parameters->map('name', fn (string $raw): string => strtoupper($raw));
        $id = $parameters->map('id', fn (string $raw): UserId => new UserId((int) $raw));

        $this->assertSame('ALICE', $name);
        $this->assertSame(42, $id->value);
        $this->assertSame('alice', $parameters->get('name'));
    }
}
