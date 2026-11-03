<?php

/**
 * Defines conversion of transport-specific input into a framework HTTP request.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Runtime;

use Phex\Http\Message\Request;

interface RequestReader
{
    public function read(): Request;
}
