<?php

/**
 * Verifies typed JSON decoding, media validation, stable input errors and safe response
 * encoding.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use InvalidArgumentException;
use JsonException;
use Phex\Examples\CompiledContainer\CreateUserDecoder;
use Phex\Http\Json\InputError;
use Phex\Http\Json\JsonInputMiddleware;
use Phex\Http\Json\JsonObject;
use Phex\Http\Json\JsonResponse;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Middleware\Pipeline;
use Phex\Tests\Compatibility\Fixtures\FailOnceHandler;
use Phex\Tests\Compatibility\Fixtures\InvalidJsonResponseHandler;
use Phex\Tests\Compatibility\Fixtures\JsonHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class JsonTest extends TestCase
{
    /** @return list<array{string, string}> */
    public static function validInputs(): array
    {
        return [
            ['{"name":"Ada"}', 'Ada'],
            [" \r\n{\"name\":\"  Ada  \"}\t", 'Ada'],
            ['{"name":"0"}', '0'],
            ['{"name":"'.str_repeat('a', 100).'"}', str_repeat('a', 100)],
            ['{"name":"Caf\u00e9 \ud83d\ude00"}', "Caf\xc3\xa9 \xf0\x9f\x98\x80"],
            ['{"na\u006de":"Alice"}', 'Alice'],
            ['{"name":"first","name":"last"}', 'last'],
            ['{"name":"a\\\\b\\"c"}', 'a\\b"c'],
        ];
    }

    #[DataProvider('validInputs')]
    public function testExplicitDecoderReturnsTheTypedDto(string $body, string $name): void
    {
        $input = (new CreateUserDecoder())->decode($body);
        $this->assertSame($name, $input->name);
    }

    /** @return list<array{string, int, string}> */
    public static function invalidInputs(): array
    {
        return [
            ['', 400, ''], [' ', 400, ''], ['{', 400, ''], ['{"name":"Ada",}', 400, ''],
            ['{"name":"Ada"} extra', 400, ''], ["{\"name\":\"a\n\"}", 400, ''],
            ["{\"name\":\"\xff\"}", 400, ''], ['{"name":"\ud800"}', 400, ''],
            ["\xef\xbb\xbf{\"name\":\"Ada\"}", 400, ''],
            ['{"name":'.str_repeat('[', 64).'0'.str_repeat(']', 64).'}', 400, ''],
            ['{}', 422, 'name'], ['{"name":null}', 422, 'name'],
            ['{"name":1}', 422, 'name'], ['{"name":1.5}', 422, 'name'],
            ['{"name":92233720368547758080}', 422, 'name'],
            ['{"name":true}', 422, 'name'], ['{"name":false}', 422, 'name'],
            ['{"name":[]}', 422, 'name'], ['{"name":{}}', 422, 'name'],
            ['{"name":""}', 422, 'name'], ['{"name":"   "}', 422, 'name'],
            ['{"name":"'.str_repeat('a', 101).'"}', 422, 'name'],
            ['{"name":"Ada\u0000"}', 422, 'name'], ['{"name":"Ada\u007f"}', 422, 'name'],
            ['{"name":"Ada\t"}', 422, 'name'],
            ['{"name":"Ada","role":"admin"}', 422, 'body'],
            ['{"name":"Ada","0":true}', 422, 'body'],
            ['[]', 422, 'body'], ['["Ada"]', 422, 'body'], ['null', 422, 'body'],
            ['true', 422, 'body'], ['false', 422, 'body'], ['"Ada"', 422, 'body'], ['0', 422, 'body'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function testMalformedDocumentsAndValidationFailuresStayDistinct(string $body, int $status, string $field): void
    {
        try {
            (new CreateUserDecoder())->decode($body);
        } catch (InputError $error) {
            $this->assertSame($status, $error->status);
            $this->assertSame($status === 400 ? 'malformed_json' : 'invalid_input', $error->error);
            if ($field !== '') {
                $this->assertTrue(array_key_exists($field, $error->errors));
            } else {
                $this->assertSame([], $error->errors);
                $this->assertTrue($error->getPrevious() instanceof JsonException);
            }

            return;
        }
        $this->fail('Invalid input must not produce a DTO.');
    }

    /** @return list<array{string, bool}> */
    public static function mediaTypes(): array
    {
        return [
            ['application/json', true], ['Application/JSON; charset=utf-8', true],
            ['application/problem+json', true], ['application/vnd.phex.user+json; version=1', true],
            ['application/a+json', true], [' text/plain ', false], ['text/json', false],
            ['application/jsonp', false], ['application/+json', false], ['application/*+json', false],
            ['application/a/b+json', false], ['application/json, application/json', false],
            ['application/'.str_repeat('a', 123).'+json', false],
        ];
    }

    #[DataProvider('mediaTypes')]
    public function testJsonMediaTypesAreCheckedBeforeDecoding(string $type, bool $accepted): void
    {
        $response = Pipeline::wrap(new JsonInputMiddleware(), new JsonHandler())->handle($this->request('{"name":"Ada"}', new Headers(['Content-Type' => [$type]])));
        $this->assertSame($accepted ? 200 : 415, $response->status);
        $this->assertSame($accepted ? '{"name":"Ada"}' : '{"error":"unsupported_media_type"}', $response->body);
        $this->assertSame(['application/json'], $response->headers->values('Content-Type'));
    }

    public function testMissingOrRepeatedContentTypeIsRejected(): void
    {
        foreach ([new Headers(), new Headers(['Content-Type' => ['application/json', 'application/json']])] as $headers) {
            $response = Pipeline::wrap(new JsonInputMiddleware(), new JsonHandler())->handle($this->request('{"name":"Ada"}', $headers));
            $this->assertSame(415, $response->status);
        }
    }

    public function testJsonErrorsHaveStableBodiesAndDoNotRetainPreviousInput(): void
    {
        $handler = Pipeline::wrap(new JsonInputMiddleware(), new JsonHandler());
        $headers = new Headers(['Content-Type' => ['application/json']]);
        $malformed = $handler->handle($this->request('{', $headers));
        $invalid = $handler->handle($this->request('{}', $headers));
        $success = $handler->handle($this->request('{"name":"Bob"}', $headers));
        $this->assertSame(400, $malformed->status);
        $this->assertSame('{"error":"malformed_json"}', $malformed->body);
        $this->assertSame(422, $invalid->status);
        $this->assertSame('{"error":"invalid_input","errors":{"name":"Expected a string."}}', $invalid->body);
        $this->assertSame(200, $success->status);
        $this->assertSame('{"name":"Bob"}', $success->body);
    }

    public function testJsonObjectExportsDefensiveCopiesAndKeepsEmptyObjectsDistinctFromLists(): void
    {
        $object = JsonObject::decode('{"name":"Ada","optional":null,"nested":{"value":7}}');
        $copy = $object->all();
        $copy['name'] = 'changed';
        $copy['nested'] = ['value' => 9];
        array_pop($copy);
        $fieldCount = count($object->all());
        $this->assertSame(3, $fieldCount);
        $this->assertSame('Ada', $object->get('name'));
        $this->assertNull($object->get('missing'));
        $this->assertNull($object->get('optional'));
        $this->assertSame(['value' => 7], $object->get('nested'));
        $this->assertSame([], JsonObject::decode('{}')->all());
    }

    public function testResponsesPreserveStatusHeadersAndExplicitOutputTypes(): void
    {
        $headers = new Headers(['Content-Type' => ['text/plain'], 'Location' => ['/users/1'], 'Set-Cookie' => ['a=1', 'b=2']]);
        $response = JsonResponse::create(['id' => 1, 'name' => 'a"b', 'enabled' => true, 'optional' => null, 'tags' => ['x']], 201, $headers);
        $this->assertSame(201, $response->status);
        $this->assertSame(['application/json'], $response->headers->values('Content-Type'));
        $this->assertSame(['text/plain'], $headers->values('Content-Type'));
        $this->assertSame(['/users/1'], $response->headers->values('Location'));
        $this->assertSame(['a=1', 'b=2'], $response->headers->values('Set-Cookie'));
        $this->assertSame('{"id":1,"name":"a\\"b","enabled":true,"optional":null,"tags":["x"]}', $response->body);
        $this->assertSame('[]', JsonResponse::create([])->body);
        $this->assertSame('[1,2]', JsonResponse::create([1, 2])->body);
    }

    /** @return list<array{mixed}> */
    public static function invalidOutputValues(): array
    {
        return [[INF], [NAN], ["\xff"]];
    }

    #[DataProvider('invalidOutputValues')]
    public function testEncodingFailuresPropagateInsteadOfEmittingPartialJson(mixed $value): void
    {
        $this->expectException(JsonException::class);
        JsonResponse::create(['value' => $value]);
    }

    public function testExcessiveOutputDepthFails(): void
    {
        $value = [];
        for ($index = 0; $index < 65; $index++) {
            $value = [$value];
        }
        $this->expectException(JsonException::class);
        JsonResponse::create($value);
    }

    public function testResponseEncodingFailuresAreNotClassifiedAsBadInput(): void
    {
        $this->expectException(JsonException::class);
        Pipeline::wrap(new JsonInputMiddleware(), new InvalidJsonResponseHandler())->handle(new Request('GET', '/'));
    }

    public function testApplicationFailuresAreNotClassifiedAsBadInput(): void
    {
        $this->expectException(RuntimeException::class);
        Pipeline::wrap(new JsonInputMiddleware(), new FailOnceHandler())->handle(new Request('GET', '/'));
    }

    public function testValidationErrorsCannotBeEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        InputError::validation([]);
    }

    private function request(string $body, Headers $headers): Request
    {
        return new Request('POST', '/users', $headers, $body);
    }
}
