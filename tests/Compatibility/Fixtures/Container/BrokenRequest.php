<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Container;

use Phex\Examples\CompiledContainer\Connection;
use Phex\Examples\CompiledContainer\RequestSession;

final class BrokenRequest
{
    public function __construct(RequestSession $session, Connection $connection)
    {
        throw new RequestConstructionFailure($session, $connection);
    }
}
