<?php

/**
 * Captures emitted statuses, headers and body writes for shared transport-independent
 * response assertions.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use LogicException;
use Phex\Contracts\Runtime\ResponseOutput;
use Phex\Http\Message\Headers;

final class HttpOutput implements ResponseOutput
{
    public int $status = 0;
    public string $body = '';
    /** @var list<string> */
    public array $events = [];
    private ?Headers $fields = null;

    public function start(int $status, Headers $headers): void
    {
        $this->status = $status;
        $this->fields = $headers;
        $this->events[] = 'start';
    }

    public function write(string $body): void
    {
        $this->events[] = 'body';
        $this->body .= $body;
    }

    public function headers(): Headers
    {
        $fields = $this->fields;
        if ($fields === null) {
            throw new LogicException('No response was started.');
        }

        return $fields;
    }
}
