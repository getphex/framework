<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use InvalidArgumentException;
use Phex\Http\Message\Headers;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HeadersTest extends TestCase
{
    public function testNamesAreCaseInsensitiveAndDuplicateFieldsKeepTheirOrder(): void
    {
        $headers = new Headers([
            'X-Trace' => ['first'],
            'x-trace' => ['second', 'third'],
        ]);

        $this->assertTrue($headers->has('X-TRACE'));
        $this->assertSame(['first', 'second', 'third'], $headers->values('x-TrAcE'));
        $this->assertSame(['x-trace' => ['first', 'second', 'third']], $headers->all());
    }

    public function testMissingHeadersHaveNoValues(): void
    {
        $headers = new Headers();

        $this->assertFalse($headers->has('missing'));
        $this->assertSame([], $headers->values('missing'));
        $this->assertSame([], $headers->all());
    }

    public function testEmptyValuesAndSeparateCookiesArePreserved(): void
    {
        $headers = new Headers([
            'X-Empty' => [''],
            'Set-Cookie' => ['a=1; Path=/', 'b=2; Path=/'],
        ]);

        $this->assertTrue($headers->has('x-empty'));
        $this->assertSame([''], $headers->values('x-empty'));
        $this->assertSame(['a=1; Path=/', 'b=2; Path=/'], $headers->values('SET-COOKIE'));
    }

    public function testUpdatesReturnIndependentHeaderSets(): void
    {
        $original = new Headers(['X-Trace' => ['first']]);
        $replaced = $original->with('X-TRACE', ['replacement']);
        $appended = $original->withAdded('x-trace', ['second']);
        $removed = $original->without('x-TRACE');

        $this->assertNotSame($original, $replaced);
        $this->assertNotSame($original, $removed);
        $this->assertSame(['first'], $original->values('X-Trace'));
        $this->assertSame(['replacement'], $replaced->values('X-Trace'));
        $this->assertSame(['first', 'second'], $appended->values('X-Trace'));
        $this->assertFalse($removed->has('X-Trace'));
    }

    public function testInputAndReturnedArraysCannotMutateTheHeaders(): void
    {
        $input = ['X-Trace' => ['first']];
        $headers = new Headers($input);
        $input['X-Trace'][0] = 'changed';
        $exported = $headers->all();
        $exported['x-trace'][0] = 'also changed';
        $values = $headers->values('X-Trace');
        $values[] = 'another';

        $this->assertSame(['first'], $headers->values('X-Trace'));
    }

    public function testAddingAndRemovingAbsentHeaders(): void
    {
        $headers = new Headers();
        $added = $headers->withAdded('X-New', ['value']);

        $this->assertSame(['value'], $added->values('x-new'));
        $this->assertSame([], $headers->without('missing')->all());
    }

    public function testRemovingAnAbsentHeaderReusesTheImmutableInstance(): void
    {
        $headers = new Headers([
            'X-Empty' => [''],
            'Set-Cookie' => ['a=1; Path=/', 'b=2; Path=/'],
        ]);
        $unchanged = $headers->without('X-MISSING');
        $changed = $unchanged->without('sEt-CoOkIe');

        $this->assertSame($headers, $unchanged);
        $this->assertNotSame($headers, $changed);
        $this->assertSame([''], $changed->values('x-empty'));
        $this->assertSame(['a=1; Path=/', 'b=2; Path=/'], $headers->values('set-cookie'));
        $this->assertSame([], $changed->values('set-cookie'));
    }

    public function testAbsentNumericHeadersAndEmptySetsAlsoReuseTheirInstance(): void
    {
        $empty = new Headers();
        $numeric = new Headers(['123' => ['value']]);

        $this->assertSame($empty, $empty->without('missing'));
        $this->assertSame($numeric, $numeric->without('456'));
        $this->assertSame(['value'], $numeric->values('123'));
    }

    public function testAllHttpTokenCharactersAreAcceptedInNames(): void
    {
        $name = '!#$%&\'*+-.^_`|~0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $headers = new Headers([$name => ['value']]);

        $this->assertSame(['value'], $headers->values($name));
    }

    public function testNumericNamesSurvivePhpArrayKeyConversion(): void
    {
        $headers = new Headers(['123' => ['first']]);
        $updated = $headers->withAdded('123', ['second']);

        $this->assertTrue($headers->has('123'));
        $this->assertSame(['first'], $headers->values('123'));
        $this->assertSame(['first', 'second'], $updated->values('123'));
        $this->assertFalse($updated->without('123')->has('123'));
    }

    /** @return list<array{string}> */
    public static function invalidNames(): array
    {
        return [
            [''], ['bad name'], ['bad:name'], ["bad\rname"], ["bad\nname"],
            ["bad\0name"], ["bad\tname"], ["bad\x7fname"], ['caf'."\xc3\xa9"],
        ];
    }

    #[DataProvider('invalidNames')]
    public function testInvalidNamesAreRejected(string $name): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Headers([$name => ['value']]);
    }

    public function testReplacementValidatesTheNewName(): void
    {
        $headers = new Headers(['X-Valid' => ['value']]);
        $this->expectException(InvalidArgumentException::class);
        $headers->with('bad name', ['value']);
    }

    public function testEmptyValueListsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Headers(['X-Empty' => []]);
    }

    public function testReplacementRejectsAnEmptyValueList(): void
    {
        $headers = new Headers(['X-Valid' => ['value']]);
        $this->expectException(InvalidArgumentException::class);
        $headers->with('X-Valid', []);
    }

    public function testControlCharactersAreRejectedExceptHorizontalTab(): void
    {
        for ($code = 0; $code < 32; $code++) {
            $rejected = false;

            try {
                new Headers(['X-Value' => ['before'.chr((int) $code).'after']]);
            } catch (InvalidArgumentException) {
                $rejected = true;
            }

            $this->assertSame($code !== 9, $rejected);
        }
    }

    public function testDeleteIsRejectedInValues(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Headers(['X-Value' => ["before\x7fafter"]]);
    }

    public function testVisibleAndExtendedBytesArePreserved(): void
    {
        $value = " \tvisible\t\x80\xff ";
        $headers = new Headers(['X-Value' => [$value]]);

        $this->assertSame([$value], $headers->values('X-Value'));
    }

    public function testAppendedValuesAreValidated(): void
    {
        $headers = new Headers(['X-Value' => ['safe']]);
        $this->expectException(InvalidArgumentException::class);
        $headers->withAdded('X-Value', ["unsafe\r\nInjected: value"]);
    }
}
