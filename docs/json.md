# JSON input and output

[Documentation](README.md)

Phex decodes JSON through explicit application DTOs and encodes explicit array payloads. This keeps the same mapping code usable in PHP and native builds.

## On this page

- [Try the skeleton endpoint](#try-the-skeleton-endpoint)
- [Decode a typed DTO](#decode-a-typed-dto)
- [JSON object rules](#json-object-rules)
- [Input errors](#input-errors)
- [Return JSON](#return-json)

## Try the skeleton endpoint

With your application's PHP server running:

```sh
curl -i http://127.0.0.1:3130/greet \
  -H 'Content-Type: application/json' --data '{"name":"Ada"}'
```

The response body is `{"hello":"Ada"}`. The skeleton already registers its decoder and wraps routing in `InputErrorMiddleware`.

## Decode a typed DTO

`App\Input\GreetingDecoder` implements `Phex\Contracts\Http\Decoder<GreetingInput>`. Its mapping follows this pattern:

```php
$name = JsonObject::decode($input)->get('name');
if (!is_string($name) || trim($name) === '' || strlen($name) > 100) {
    throw InputError::validation(['name' => 'Expected a nonempty string of at most 100 UTF-8 bytes.']);
}
return new GreetingInput(trim($name));
```

Here `JsonObject` and the JSON `InputError` factories come from `Phex\Http\Json`. The decoder's native return type is `GreetingInput`, with `/** @implements Decoder<GreetingInput> */` on the class.

Inside `GreetAction`, the generic body helper preserves that concrete result:

```php
$input = JsonBody::decode(
    $request,
    fn (string $body): GreetingInput => $this->decoder->decode($body),
);
```

Use a closure literal with explicit parameter and return types to keep PHPStan and ElephC inference aligned. DTO validation stays in your decoder. Unknown-field policy is also explicit; the skeleton's greeting decoder validates `name` but does not automatically reject other fields.

## JSON object rules

`JsonObject::decode` parses at depth 64 and requires an object root. `{}` is an object; `[]`, strings and numeric roots fail shape validation. Nested values use associative decoding. Missing and null fields both return null from `get`; your domain mapper chooses the required-field policy.

Invalid JSON, invalid UTF-8 and excessive depth are malformed input. Numbers retain numeric types, so a large unquoted JSON number cannot silently pass a string-field check. Duplicate keys use the final decoded value.

`JsonBody::decode` requires exactly one `Content-Type` value. It accepts `application/json` and valid application subtypes ending in `+json`, case-insensitively and with parameters allowed. Input is UTF-8; a charset parameter does not request transcoding.

## Input errors

The skeleton's `Phex\Http\Input\InputErrorMiddleware` handles JSON and query failures:

| Status | Error | Example |
| --- | --- | --- |
| `400` | `malformed_json` | A truncated JSON document |
| `415` | `unsupported_media_type` | Missing or unsupported content type |
| `422` | `invalid_input` | Valid JSON with an invalid field or root shape |

For example, posting `{}` returns:

```json
{"error":"invalid_input","errors":{"name":"Expected a nonempty string of at most 100 UTF-8 bytes."}}
```

Malformed-input responses expose their stable error code. Unexpected decoder errors propagate to the generic server-error policy. Keep validation exceptions deliberate rather than catching every exception as invalid input.

## Return JSON

Map DTOs to arrays and pass them to `Phex\Http\Json\JsonResponse`:

```php
return JsonResponse::create(['id' => $user->id, 'name' => $user->name], status: 201);
```

The helper sets `application/json`. Lists remain lists and an empty array encodes as `[]`. It preserves the supplied status and other headers. For a resource location, provide a `Headers` value containing the result of [named URL generation](urls.md).

Encoding uses exception-based error handling at depth 64. Invalid UTF-8, non-finite numbers and excessive nesting fail before response emission; they are application failures rather than client validation errors.

---

[Previous: Requests and responses](requests-responses.md) | [Next: Query strings](query-strings.md)
