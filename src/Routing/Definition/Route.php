<?php

declare(strict_types=1);

namespace Phex\Routing\Definition;

use InvalidArgumentException;
use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Message\Token;

final readonly class Route
{
    public PathPattern $pattern;

    public function __construct(
        public string $method,
        public string $path,
        public Endpoint $endpoint,
    ) {
        if (!Token::isValid($method)) {
            throw new InvalidArgumentException('Invalid route HTTP method.');
        }

        $this->pattern = new PathPattern($path);
    }
}
