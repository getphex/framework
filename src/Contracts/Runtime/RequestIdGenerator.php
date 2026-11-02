<?php

declare(strict_types=1);

namespace Phex\Contracts\Runtime;

use Phex\Http\Logging\RequestId;

interface RequestIdGenerator
{
    public function generate(): RequestId;
}
