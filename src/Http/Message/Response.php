<?php

/**
 * Represents an immutable buffered HTTP response and validates its status and header
 * updates.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Message;

use InvalidArgumentException;

final readonly class Response
{
    public function __construct(
        public int $status = 200,
        public Headers $headers = new Headers(),
        public string $body = '',
    ) {
        if ($status < 100 || $status > 599) {
            throw new InvalidArgumentException('HTTP status must be between 100 and 599.');
        }
    }

    public function withStatus(int $status): self
    {
        return new self($status, $this->headers, $this->body);
    }

    public function withHeaders(Headers $headers): self
    {
        return new self($this->status, $headers, $this->body);
    }

    public function withBody(string $body): self
    {
        return new self($this->status, $this->headers, $body);
    }
}
