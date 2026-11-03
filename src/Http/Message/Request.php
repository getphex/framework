<?php

/**
 * Represents an immutable HTTP request with validated method, target, headers and buffered
 * body bytes.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Message;

use InvalidArgumentException;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $uri,
        public Headers $headers = new Headers(),
        public string $body = '',
    ) {
        if (!Token::isValid($method)) {
            throw new InvalidArgumentException('Invalid HTTP method.');
        }

        if ($uri === '' || str_contains($uri, '#')) {
            throw new InvalidArgumentException('Invalid HTTP request URI.');
        }

        for ($index = 0; $index < strlen($uri); $index++) {
            $byte = ord($uri[$index]);

            if ($byte <= 32 || $byte === 127) {
                throw new InvalidArgumentException('Invalid character in HTTP request URI.');
            }
        }
    }

    public function withMethod(string $method): self
    {
        return new self($method, $this->uri, $this->headers, $this->body);
    }

    public function withUri(string $uri): self
    {
        return new self($this->method, $uri, $this->headers, $this->body);
    }

    public function withHeaders(Headers $headers): self
    {
        return new self($this->method, $this->uri, $headers, $this->body);
    }

    public function withBody(string $body): self
    {
        return new self($this->method, $this->uri, $this->headers, $body);
    }
}
