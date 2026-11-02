<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Logging;

use Phex\Contracts\Runtime\RequestIdGenerator;
use Phex\Http\Logging\RequestId;

final class SequentialRequestIds implements RequestIdGenerator
{
    private int $sequence = 0;

    public function generate(): RequestId
    {
        return new RequestId('request-'.++$this->sequence);
    }
}
