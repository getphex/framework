<?php

/**
 * Captures the trusted correlation ID and method belonging to one HTTP request.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Logging;

use LogicException;
use Phex\Http\Message\Request;

final readonly class RequestLogContext
{
    public function __construct(public RequestId $id, public string $method) {}

    /** The HTTP host replaces inbound IDs before entering the application. */
    public static function create(Request $request): RequestLogContext
    {
        $values = $request->headers->values('X-Request-Id');
        if (count($values) !== 1) {
            throw new LogicException('The HTTP host must supply one request ID.');
        }

        return new RequestLogContext(new RequestId($values[0]), $request->method);
    }
}
