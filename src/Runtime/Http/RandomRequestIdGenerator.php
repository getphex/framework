<?php

/**
 * Creates trusted random correlation identifiers independently of client-supplied request
 * headers.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Http;

use Phex\Contracts\Runtime\RequestIdGenerator;
use Phex\Http\Logging\RequestId;

final class RandomRequestIdGenerator implements RequestIdGenerator
{
    public function generate(): RequestId
    {
        return new RequestId(sprintf('%08x%08x%08x%08x',
            random_int(0, 2147483647), random_int(0, 2147483647),
            random_int(0, 2147483647), random_int(0, 2147483647)));
    }
}
