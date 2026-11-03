<?php

/**
 * Verifies bounded query decoding, duplicate preservation, strict scalar conversion and
 * typed input errors.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use App\Config\ApplicationSettings;
use App\Input\SearchDecoder;
use App\Input\SearchInput;
use InvalidArgumentException;
use JsonException;
use Phex\Http\Input\InputError;
use Phex\Http\Input\InputErrorMiddleware;
use Phex\Http\Json\JsonInputMiddleware;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Middleware\Pipeline;
use Phex\Http\Query\QueryParameters;
use Phex\Http\Query\QueryString;
use Phex\Tests\Compatibility\Fixtures\FailOnceHandler;
use Phex\Tests\Compatibility\Fixtures\InvalidJsonResponseHandler;
use Phex\Tests\Compatibility\Fixtures\JsonHandler;
use Phex\Tests\Compatibility\Fixtures\QueryHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class QueryTest extends TestCase
{
    public function testQueryExtractionUsesTheRequestUriAndKeepsOtherRequestData(): void
    {
        $request = new Request('POST', '/files/a%3Fb?page=2?tail', new Headers(['X-Test' => ['unchanged']]), 'body');
        $this->assertSame('page=2?tail', QueryString::raw($request));
        $this->assertSame('2?tail', QueryParameters::fromRequest($request)->get('page'));
        $this->assertSame([], QueryParameters::fromRequest(new Request('GET', '/files/a%3Fb'))->all());
        $this->assertSame([], QueryParameters::fromRequest(new Request('GET', '/files?'))->all());
        $this->assertSame(['unchanged'], $request->headers->values('X-Test'));
        $this->assertSame('body', $request->body);
    }

    /** @return list<array{string, string, string}> */
    public static function decodedPairs(): array
    {
        return [
            ['name=Ada', 'name', 'Ada'], ['flag', 'flag', ''], ['flag=', 'flag', ''],
            ['=value', '', 'value'], ['=', '', ''],
            ['q=a+b%2Bc', 'q', 'a b+c'], ['q=%252F', 'q', '%2F'],
            ['q=a%26b%3Dc=d', 'q', 'a&b=c=d'], ['q=a;b', 'q', 'a;b'],
            ['first.name=Ada', 'first.name', 'Ada'], ['first+name=Ada', 'first name', 'Ada'],
            ['tags%5B%5D=a', 'tags[]', 'a'], ['nested[key]=a', 'nested[key]', 'a'],
            ['%6Eame=Caf%c3%a9', 'name', "Caf\xc3\xa9"], ['q=%FF', 'q', "\xff"],
        ];
    }

    #[DataProvider('decodedPairs')]
    public function testFormStyleDecodingPreservesLiteralNamesAndDecodesOnlyOnce(string $raw, string $name, string $value): void
    {
        $query = QueryParameters::decode($raw);
        $this->assertSame($value, $query->get($name));
        $this->assertSame([$value], $query->values($name));
        $this->assertSame($name, $query->all()[0]->name);
    }

    public function testRepeatedPairsPreserveTheirOrderAndNumericNamesStayStrings(): void
    {
        $query = QueryParameters::decode('0=zero&tag=a&01=leading&tag=&0=again&tag=b');
        $names = [];
        $values = [];
        foreach ($query->all() as $pair) {
            $names[] = $pair->name;
            $values[] = $pair->value;
        }
        $this->assertSame(['0', 'tag', '01', 'tag', '0', 'tag'], $names);
        $this->assertSame(['zero', 'a', 'leading', '', 'again', 'b'], $values);
        $this->assertSame(['a', '', 'b'], $query->values('tag'));
        $this->assertSame(['zero', 'again'], $query->values('0'));
        $this->assertSame('leading', $query->get('01'));
    }

    public function testMissingEmptyAndDefaultValuesRemainDistinctAndExportsAreCopies(): void
    {
        $query = QueryParameters::decode('empty=&zero=0&disabled=false');
        $this->assertFalse($query->has('missing'));
        $this->assertTrue($query->has('empty'));
        $this->assertSame([], $query->values('missing'));
        $this->assertSame('', $query->get('empty', 'fallback'));
        $this->assertSame('fallback', $query->get('missing', 'fallback'));
        $this->assertSame('', $query->get('missing', ''));
        $this->assertSame(0, $query->integer('zero', 5));
        $this->assertSame(0, $query->integer('missing', 0));
        $this->assertFalse($query->boolean('disabled', true));
        $this->assertFalse($query->boolean('missing', false));
        $copy = $query->all();
        array_pop($copy);
        $values = $query->values('empty');
        $values[0] = 'changed';
        $this->assertCount(3, $query->all());
        $this->assertSame('', $query->get('empty'));
    }

    public function testRequiredMissingStringsRaiseValidationErrors(): void
    {
        try {
            QueryParameters::decode('')->get('name');
        } catch (InputError $error) {
            $this->assertSame(422, $error->status);
            $this->assertSame('invalid_input', $error->error);
            $this->assertSame(['query.name' => 'Required query parameter.'], $error->errors);

            return;
        }
        $this->fail('Missing query input must be rejected.');
    }

    /** @return list<array{string, string}> */
    public static function scalarReaders(): array
    {
        return [
            ['get', 'value=1&%76alue=1'], ['integer', 'value=1&%76alue=1'], ['boolean', 'value=1&%76alue=1'],
            ['get', 'value=&value=1'], ['integer', 'value=&value=1'], ['boolean', 'value=&value=1'],
        ];
    }

    #[DataProvider('scalarReaders')]
    public function testScalarReadersRejectRepeatedValuesEvenWhenIdenticalOrDefaulted(string $reader, string $raw): void
    {
        $query = QueryParameters::decode($raw);
        try {
            if ($reader === 'get') {
                $query->get('value', 'default');
            } elseif ($reader === 'integer') {
                $query->integer('value', 9);
            } else {
                $query->boolean('value', false);
            }
        } catch (InputError $error) {
            $this->assertSame(422, $error->status);
            $this->assertSame(['query.value' => 'Expected a single query parameter.'], $error->errors);

            return;
        }
        $this->fail('Scalar query readers must reject duplicates.');
    }

    /** @return list<array{string, int}> */
    public static function integers(): array
    {
        return [
            ['0', 0], ['-0', 0], ['000', 0], ['42', 42], ['0042', 42],
            ['-42', -42], ['-0042', -42], [(string) PHP_INT_MAX, PHP_INT_MAX],
            [(string) PHP_INT_MIN, PHP_INT_MIN],
        ];
    }

    #[DataProvider('integers')]
    public function testSignedDecimalIntegersHaveCheckedBounds(string $raw, int $expected): void
    {
        $this->assertSame($expected, QueryParameters::decode('n='.$raw)->integer('n'));
    }

    /** @return list<array{string}> */
    public static function invalidIntegers(): array
    {
        return [
            [''], ['-'], ['%2B42'], ['+42'], ['42+'], ['4.2'], ['4e2'], ['0x2a'],
            ['42x'], [(string) PHP_INT_MAX.'0'], [(string) PHP_INT_MIN.'0'],
        ];
    }

    #[DataProvider('invalidIntegers')]
    public function testInvalidIntegersNeverFallBackToDefaults(string $raw): void
    {
        $this->expectException(InputError::class);
        QueryParameters::decode('n='.$raw)->integer('n', 5);
    }

    public function testMissingIntegersWithoutDefaultsAreInvalidInput(): void
    {
        $this->expectException(InputError::class);
        QueryParameters::decode('')->integer('missing');
    }

    /** @return list<array{string, bool}> */
    public static function booleans(): array
    {
        return [['true', true], ['1', true], ['false', false], ['0', false]];
    }

    #[DataProvider('booleans')]
    public function testBooleanConversionUsesAnExplicitVocabulary(string $raw, bool $expected): void
    {
        $this->assertSame($expected, QueryParameters::decode('enabled='.$raw)->boolean('enabled'));
    }

    /** @return list<array{string}> */
    public static function invalidBooleans(): array
    {
        return [[''], ['TRUE'], ['False'], ['yes'], ['no'], ['on'], ['off'], ['2'], ['+true']];
    }

    #[DataProvider('invalidBooleans')]
    public function testInvalidBooleansNeverFallBackToDefaults(string $raw): void
    {
        $this->expectException(InputError::class);
        QueryParameters::decode('enabled='.$raw)->boolean('enabled', false);
    }

    public function testMissingBooleansWithoutDefaultsAreInvalidInput(): void
    {
        $this->expectException(InputError::class);
        QueryParameters::decode('')->boolean('missing');
    }

    /** @return list<array{string}> */
    public static function malformedQueries(): array
    {
        return [['q=%'], ['q=%2'], ['q=%GG'], ['%=x'], ['q=%00'], ['%0A=x'], ['q=%7f'], ["q=a\tb"]];
    }

    #[DataProvider('malformedQueries')]
    public function testMalformedEscapesAndDecodedControlsAreBadRequests(string $raw): void
    {
        try {
            QueryParameters::decode($raw);
        } catch (InputError $error) {
            $this->assertSame(400, $error->status);
            $this->assertSame('malformed_query', $error->error);
            $this->assertSame([], $error->errors);

            return;
        }
        $this->fail('Malformed query bytes must be rejected.');
    }

    public function testLimitsCountEncodedBytesAndEveryPairIncludingDuplicates(): void
    {
        $this->assertSame('2', QueryParameters::decode('a=1&b=2', 7, 2)->get('b'));
        $this->assertSame(['1', '2'], QueryParameters::decode('&a=1&&a=2&', 10, 2)->values('a'));
        $this->assertSame([], QueryParameters::decode('&&', 2, 1)->all());
        foreach (['a=1&b=2&c=3', 'a=1&a=2&a=3', 'a=1&=2&=3'] as $raw) {
            try {
                QueryParameters::decode($raw, 64, 2);
            } catch (InputError $error) {
                $this->assertSame(400, $error->status);
                $this->assertSame('query_limit_exceeded', $error->error);
                continue;
            }
            $this->fail('Query parsing must count all nonempty pairs.');
        }
    }

    public function testEncodedBytesAreBoundedBeforeDecoding(): void
    {
        $this->expectException(InputError::class);
        QueryParameters::decode('a=%25252525', 10, 2);
    }

    public function testRequestParsingRespectsConfiguredLimits(): void
    {
        $this->expectException(InputError::class);
        QueryParameters::fromRequest(new Request('GET', '/search?a=1&b=2'), maxParameters: 1);
    }

    public function testInvalidLimitsAreProgrammingErrors(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryParameters::decode('', maxBytes: 0);
    }

    public function testInvalidParameterLimitIsAProgrammingError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        QueryParameters::decode('', maxParameters: -1);
    }

    public function testDtoDecodersRetainTheirConcreteGenericResult(): void
    {
        $decoder = new SearchDecoder(new ApplicationSettings('world', 20, false));
        $input = QueryString::decode(new Request('GET', '/search?page=2&limit=10&active=true&sort=desc'),
            fn (string $query): SearchInput => $decoder->decode($query));
        $this->assertSame(2, $input->page);
        $this->assertSame(10, $input->limit);
        $this->assertTrue($input->active);
        $this->assertSame('desc', $input->sort);
    }

    public function testInputErrorsAndRecoveryUseTheSameJsonPolicy(): void
    {
        $application = Pipeline::wrap(new InputErrorMiddleware(), new QueryHandler());
        $malformed = $application->handle(new Request('GET', '/search?page=%GG'));
        $invalid = $application->handle(new Request('GET', '/search?page=one'));
        $duplicate = $application->handle(new Request('GET', '/search?page=1&page=2'));
        $success = $application->handle(new Request('GET', '/search?page=3&active=1'));
        $defaulted = $application->handle(new Request('GET', '/search'));
        $this->assertSame(400, $malformed->status);
        $this->assertSame('{"error":"malformed_query"}', $malformed->body);
        $this->assertSame(422, $invalid->status);
        $this->assertSame('{"error":"invalid_input","errors":{"query.page":"Expected a signed decimal integer."}}', $invalid->body);
        $this->assertSame(422, $duplicate->status);
        $this->assertSame(200, $success->status);
        $this->assertSame('{"page":3,"limit":20,"active":true,"sort":"asc"}', $success->body);
        $this->assertSame('{"page":1,"limit":20,"active":false,"sort":"asc"}', $defaulted->body);
        $this->assertSame(['application/json'], $invalid->headers->values('Content-Type'));
    }

    public function testExistingJsonMiddlewareAlsoHandlesSharedQueryErrors(): void
    {
        $response = Pipeline::wrap(new JsonInputMiddleware(), new QueryHandler())->handle(new Request('GET', '/search?active=no'));
        $this->assertSame(422, $response->status);
    }

    public function testCommonMiddlewareStillHandlesTheOriginalJsonErrorType(): void
    {
        $request = new Request('POST', '/users', new Headers(['Content-Type' => ['application/json']]), '{');
        $response = Pipeline::wrap(new InputErrorMiddleware(), new JsonHandler())->handle($request);
        $this->assertSame(400, $response->status);
        $this->assertSame('{"error":"malformed_json"}', $response->body);
    }

    public function testCommonMiddlewareLeavesUnexpectedApplicationErrorsUntouched(): void
    {
        $this->expectException(RuntimeException::class);
        Pipeline::wrap(new InputErrorMiddleware(), new FailOnceHandler())->handle(new Request('GET', '/'));
    }

    public function testCommonMiddlewareLeavesOutputEncodingErrorsUntouched(): void
    {
        $this->expectException(JsonException::class);
        Pipeline::wrap(new InputErrorMiddleware(), new InvalidJsonResponseHandler())->handle(new Request('GET', '/'));
    }

    public function testCommonValidationErrorsRequireAnErrorField(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputError::validation([]);
    }
}
