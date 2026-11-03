# Query strings

[Documentation](README.md)

Query input is decoded explicitly from the immutable request URI. Repeated pairs stay ordered, names stay literal and conversions have defined failure behavior.

## On this page

- [Read query values](#read-query-values)
- [Missing, empty and repeated input](#missing-empty-and-repeated-input)
- [Conversion and decoding rules](#conversion-and-decoding-rules)
- [Bound input](#bound-input)
- [Decode an application DTO](#decode-an-application-dto)

## Read query values

Inside an endpoint:

```php
use Phex\Http\Query\QueryParameters;

$query = QueryParameters::fromRequest($request);
$page = $query->integer('page', 1);
$active = $query->boolean('active', false);
$sort = $query->get('sort', 'asc');
$tags = $query->values('tag');
```

For `/search?page=2&active=true&tag=php&tag=native`, this returns page `2`, active `true` and tags `['php', 'native']`.

## Missing, empty and repeated input

`get`, `integer` and `boolean` require a single value. Missing values use the explicit default or raise `422 invalid_input`. Defaults do not replace present empty values. An empty string can be read with `get`; it fails integer or boolean conversion.

Repeating a scalar name is invalid even when the values are identical. `values` returns all values for a name or an empty list when absent. `has` distinguishes an absent name from a present empty value.

`all()` returns immutable `QueryParameter` values with `name` and `value` properties. It does not create nested arrays from bracket syntax or normalize dots and spaces in names.

## Conversion and decoding rules

Integers use checked signed decimal parsing. Leading zeros and negative zero are accepted; plus signs, whitespace, fractions, exponents and overflow are rejected. Booleans accept exactly `true`, `false`, `1` and `0`.

Parsing uses `&` separators and the first `=` in a pair. A bare name has an empty value. `+` becomes a space, and percent escapes are decoded once. This differs from path decoding, where a literal plus stays a plus.

Malformed escapes and decoded ASCII controls become `400 malformed_query`. Other values remain byte strings; validate the domain's encoding and shape in its decoder.

## Bound input

Defaults allow 8,192 encoded query bytes and 100 nonempty pairs. Exceeding either limit returns `400 query_limit_exceeded`.

To choose smaller application limits:

```php
$query = QueryParameters::fromRequest($request, maxBytes: 4096, maxParameters: 40);
```

Limits must be positive. Reading query values is optional; the router itself ignores the query while selecting a path.

## Decode an application DTO

The skeleton's `SearchDecoder` implements `Decoder<SearchInput>`. It reads page, limit, active and sort, then checks domain bounds and unknown fields. `SearchAction` preserves the DTO type through:

```php
$input = QueryString::decode(
    $request,
    fn (string $query): SearchInput => $this->decoder->decode($query),
);
```

Import `QueryString` from `Phex\Http\Query`. The callback receives the encoded query without `?`. `InputErrorMiddleware` maps its deliberate validation failures to the same JSON error policy used for [body input](json.md#input-errors).

---

[Previous: JSON input and output](json.md) | [Next: URL generation](urls.md)
