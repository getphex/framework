<?php

/**
 * Decodes a JSON object root and exposes validated, independent field snapshots for
 * explicit DTO mapping.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Json;

use JsonException;

/** A decoded object for explicit DTO mapping; nested values retain associative decoding. */
final readonly class JsonObject
{
    /** @param array<int|string, mixed> $values */
    private function __construct(private array $values) {}

    public static function decode(string $input): JsonObject
    {
        try {
            $value = json_decode($input, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw InputError::malformed($error);
        }

        // Associative decoding makes {} and [] identical. Check the root token after parsing.
        $document = ltrim($input, " \t\r\n");
        if (!is_array($value) || !str_starts_with($document, '{')) {
            throw InputError::validation(['body' => 'Expected a JSON object.']);
        }

        // The native boundary needs an explicit array conversion after the mixed guard.
        return new JsonObject((array) $value);
    }

    public function get(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    /** @return array<int|string, mixed> */
    public function all(): array
    {
        $snapshot = [];
        foreach ($this->values as $name => $value) {
            $snapshot[$name] = $value;
        }

        return $snapshot;
    }
}
