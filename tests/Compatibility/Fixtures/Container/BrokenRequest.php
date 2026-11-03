<?php

/**
 * Throws during request service construction after receiving session and application
 * dependencies.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

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
