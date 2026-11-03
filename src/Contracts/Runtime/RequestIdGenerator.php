<?php

/**
 * Defines creation of trusted correlation identifiers for incoming HTTP requests.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Runtime;

use Phex\Http\Logging\RequestId;

interface RequestIdGenerator
{
    public function generate(): RequestId;
}
