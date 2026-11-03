<?php

/**
 * Parses and validates route segments and supports trusted build-validated segment
 * metadata.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Routing\Definition;

use InvalidArgumentException;
use Phex\Routing\Parameter\Parameters;

final readonly class PathPattern
{
    /** @var list<Segment> */
    private array $segments;

    /** @param list<Segment>|null $compiledSegments Prevalidated metadata for trusted build artifacts only. */
    public function __construct(public string $path, ?array $compiledSegments = null)
    {
        if ($compiledSegments !== null) {
            $this->segments = $compiledSegments;

            return;
        }
        if (!str_starts_with($path, '/') || str_contains($path, '?') || str_contains($path, '#')) {
            throw new InvalidArgumentException('Route patterns must be absolute paths without query or fragment.');
        }

        for ($index = 0; $index < strlen($path); $index++) {
            $byte = ord($path[$index]);

            if ($byte < 32 || $byte === 127) {
                throw new InvalidArgumentException('Route patterns cannot contain control characters.');
            }
        }

        $segments = [];
        $names = [];
        $parts = $path === '/' ? [] : explode('/', substr($path, 1));

        foreach ($parts as $part) {
            if (str_starts_with($part, '{') && str_ends_with($part, '}')) {
                $name = substr($part, 1, -1);

                if (!self::validName($name) || isset($names[$name])) {
                    throw new InvalidArgumentException('Invalid or repeated route parameter name.');
                }

                $names[$name] = true;
                $segments[] = new Segment($name, true);
            } else {
                if (str_contains($part, '{') || str_contains($part, '}')) {
                    throw new InvalidArgumentException('Route parameters must occupy a complete path segment.');
                }

                $segments[] = new Segment($part, false);
            }
        }

        $this->segments = $segments;
    }

    /** @return list<Segment> */
    public function segments(): array
    {
        return $this->segments;
    }

    /** @param list<string> $parts */
    public function match(array $parts): ?Parameters
    {
        if (count($parts) !== count($this->segments)) {
            return null;
        }

        $values = [];

        foreach ($this->segments as $index => $segment) {
            $value = (string) $parts[$index];

            if ($segment->parameter) {
                if ($value === '') {
                    return null;
                }

                $values[$segment->value] = $value;
            } elseif ($segment->value !== $value) {
                return null;
            }
        }

        return new Parameters($values);
    }

    public function precedes(self $other): bool
    {
        $length = min(count($this->segments), count($other->segments));

        for ($index = 0; $index < $length; $index++) {
            $left = $this->segments[$index];
            $right = $other->segments[$index];

            if ($left->parameter !== $right->parameter) {
                return !$left->parameter;
            }
        }

        return false;
    }

    public function sameShape(self $other): bool
    {
        if (count($this->segments) !== count($other->segments)) {
            return false;
        }

        foreach ($this->segments as $index => $left) {
            $right = $other->segments[$index];

            if ($left->parameter !== $right->parameter
                || (!$left->parameter && $left->value !== $right->value)) {
                return false;
            }
        }

        return true;
    }

    private static function validName(string $name): bool
    {
        if ($name === '') {
            return false;
        }

        for ($index = 0; $index < strlen($name); $index++) {
            $byte = ord($name[$index]);

            if (($byte >= 65 && $byte <= 90) || ($byte >= 97 && $byte <= 122)
                || $byte === 95 || ($index > 0 && $byte >= 48 && $byte <= 57)) {
                continue;
            }

            return false;
        }

        return true;
    }
}
