<?php

declare(strict_types=1);

namespace Phex\Http\Query;

use InvalidArgumentException;
use Phex\Http\Input\InputError;
use Phex\Http\Message\Request;
use Phex\Support\IntegerParser;

/** Immutable ordered byte-string pairs with explicit scalar conversions. */
final readonly class QueryParameters
{
    /** @param list<QueryParameter> $parameters */
    private function __construct(private array $parameters) {}

    public static function fromRequest(Request $request, int $maxBytes = 8192, int $maxParameters = 100): QueryParameters
    {
        return self::decode(QueryString::raw($request), $maxBytes, $maxParameters);
    }

    public static function decode(string $input, int $maxBytes = 8192, int $maxParameters = 100): QueryParameters
    {
        if ($maxBytes < 1 || $maxParameters < 1) {
            throw new InvalidArgumentException('Query limits must be positive.');
        }
        if (strlen($input) > $maxBytes) {
            throw InputError::queryLimitExceeded();
        }
        $parameters = [];
        foreach (explode('&', $input) as $pair) {
            if ($pair === '') {
                continue;
            }
            if (count($parameters) >= $maxParameters) {
                throw InputError::queryLimitExceeded();
            }
            $separator = strpos($pair, '=');
            $name = $separator === false ? $pair : substr($pair, 0, $separator);
            $value = $separator === false ? '' : substr($pair, $separator + 1);
            $parameters[] = new QueryParameter(self::decodeComponent($name), self::decodeComponent($value));
        }

        return new QueryParameters($parameters);
    }

    public function has(string $name): bool
    {
        foreach ($this->parameters as $parameter) {
            if ($parameter->name === $name) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function values(string $name): array
    {
        $values = [];
        foreach ($this->parameters as $parameter) {
            if ($parameter->name === $name) {
                $values[] = $parameter->value;
            }
        }

        return $values;
    }

    /** @return list<QueryParameter> */
    public function all(): array
    {
        return $this->parameters;
    }

    public function get(string $name, ?string $default = null): string
    {
        $value = $this->single($name);
        if ($value !== null) {
            return $value;
        }
        if ($default !== null) {
            return $default;
        }

        throw InputError::validation(['query.'.$name => 'Required query parameter.']);
    }

    public function integer(string $name, ?int $default = null): int
    {
        $raw = $this->single($name);
        if ($raw === null) {
            if ($default !== null) {
                return $default;
            }

            throw InputError::validation(['query.'.$name => 'Required query parameter.']);
        }
        $value = IntegerParser::parse($raw);
        if ($value === null) {
            throw InputError::validation(['query.'.$name => 'Expected a signed decimal integer.']);
        }

        return $value;
    }

    public function boolean(string $name, ?bool $default = null): bool
    {
        $raw = $this->single($name);
        if ($raw === null) {
            if ($default !== null) {
                return $default;
            }

            throw InputError::validation(['query.'.$name => 'Required query parameter.']);
        }
        if ($raw === 'true' || $raw === '1') {
            return true;
        }
        if ($raw === 'false' || $raw === '0') {
            return false;
        }

        throw InputError::validation(['query.'.$name => 'Expected true, false, 1 or 0.']);
    }

    private function single(string $name): ?string
    {
        $value = null;
        foreach ($this->parameters as $parameter) {
            if ($parameter->name === $name) {
                if ($value !== null) {
                    throw InputError::validation(['query.'.$name => 'Expected a single query parameter.']);
                }
                $value = $parameter->value;
            }
        }

        return $value;
    }

    private static function decodeComponent(string $input): string
    {
        $decoded = '';
        for ($index = 0; $index < strlen($input); $index++) {
            $character = $input[$index];
            if ($character === '+') {
                $character = ' ';
            } elseif ($character === '%') {
                if ($index + 2 >= strlen($input)) {
                    throw InputError::malformedQuery();
                }
                $high = self::hex($input[$index + 1]);
                $low = self::hex($input[$index + 2]);
                if ($high < 0 || $low < 0) {
                    throw InputError::malformedQuery();
                }
                $character = chr($high * 16 + $low);
                $index += 2;
            }
            $byte = ord($character);
            if ($byte < 32 || $byte === 127) {
                throw InputError::malformedQuery();
            }
            $decoded .= $character;
        }

        return $decoded;
    }

    private static function hex(string $character): int
    {
        $byte = ord($character);
        if ($byte >= 48 && $byte <= 57) {
            return $byte - 48;
        }
        if ($byte >= 65 && $byte <= 70) {
            return $byte - 55;
        }
        if ($byte >= 97 && $byte <= 102) {
            return $byte - 87;
        }

        return -1;
    }
}
