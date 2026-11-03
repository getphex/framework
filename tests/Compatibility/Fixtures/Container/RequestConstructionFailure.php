<?php

/**
 * Retains partially constructed request dependencies so tests can inspect their cleanup
 * after failure.
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
use RuntimeException;

final class RequestConstructionFailure extends RuntimeException
{
    public function __construct(public readonly RequestSession $session, public readonly Connection $connection)
    {
        parent::__construct('Request construction failed.');
    }
}
