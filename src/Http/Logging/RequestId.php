<?php

/**
 * Validates an immutable correlation identifier before it enters request headers or log
 * records.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Logging;

use InvalidArgumentException;

final readonly class RequestId
{
    public function __construct(public string $value)
    {
        if ($value === '' || strlen($value) > 128) {
            throw new InvalidArgumentException('Invalid request ID length.');
        }
        for ($index = 0; $index < strlen($value); $index++) {
            $byte = ord($value[$index]);
            if (!(($byte >= 48 && $byte <= 57) || ($byte >= 65 && $byte <= 90)
                || ($byte >= 97 && $byte <= 122) || $value[$index] === '-' || $value[$index] === '_')) {
                throw new InvalidArgumentException('Invalid request ID character.');
            }
        }
    }
}
