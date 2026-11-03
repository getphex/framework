<?php

/**
 * Throws from route dispatch to verify application errors propagate through the routing
 * boundary.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Routing\Endpoint;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Routing\Parameter\Parameters;
use RuntimeException;

final class FailingEndpoint implements Endpoint
{
    public function handle(Request $_request, Parameters $_parameters): Response
    {
        throw new RuntimeException('endpoint failed');
    }
}
