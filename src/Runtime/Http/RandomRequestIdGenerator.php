<?php

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
