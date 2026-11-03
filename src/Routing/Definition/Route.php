<?php

/**
 * Defines an immutable route's method, pattern, endpoint and optional name for matching
 * and URL generation.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Routing\Definition;

use InvalidArgumentException;
use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Message\Token;

final readonly class Route
{
    public PathPattern $pattern;

    /** @param list<Segment>|null $compiledSegments Prevalidated metadata for trusted build artifacts only. */
    public function __construct(
        public string $method,
        public string $path,
        public Endpoint $endpoint,
        ?array $compiledSegments = null,
        public ?string $name = null,
    ) {
        if (!Token::isValid($method)) {
            throw new InvalidArgumentException('Invalid route HTTP method.');
        }

        $this->pattern = new PathPattern($path, $compiledSegments);
        if ($compiledSegments !== null) {
            return;
        }
        if ($name !== null) {
            if (!self::validName($name)) {
                throw new InvalidArgumentException('Invalid route name.');
            }
            if (str_starts_with($path, '//')) {
                throw new InvalidArgumentException('Named routes require a single leading slash.');
            }
            foreach ($this->pattern->segments() as $segment) {
                if (!$segment->parameter && ($segment->value === '.' || $segment->value === '..')) {
                    throw new InvalidArgumentException('Named routes cannot contain dot segments.');
                }
            }
        }
    }

    private static function validName(string $name): bool
    {
        if ($name === '' || strlen($name) > 128) {
            return false;
        }
        for ($index = 0; $index < strlen($name); $index++) {
            $byte = ord($name[$index]);
            $letter = ($byte >= 65 && $byte <= 90) || ($byte >= 97 && $byte <= 122) || $byte === 95;
            if (!$letter && !($index > 0 && (($byte >= 48 && $byte <= 57) || $byte === 46 || $byte === 45))) {
                return false;
            }
        }

        return true;
    }
}
