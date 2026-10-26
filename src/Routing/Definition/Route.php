<?php

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
    ) {
        if (!Token::isValid($method)) {
            throw new InvalidArgumentException('Invalid route HTTP method.');
        }

        $this->pattern = new PathPattern($path, $compiledSegments);
    }
}
