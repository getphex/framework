<?php

/**
 * Reads native request globals and normalizes the transport's empty input-body behavior.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Elephc;

use Phex\Contracts\Runtime\RequestReader;
use Phex\Http\Message\Request;
use Phex\Runtime\Fpm\ServerRequest;

/** Request input for the ElephC --web bridge. */
final class NativeRequestReader implements RequestReader
{
    public function read(): Request
    {
        $body = file_get_contents('php://input');
        // ElephC 0.27.6 reports an empty native request body as false.
        return ServerRequest::create($_SERVER, $body === false ? '' : $body);
    }
}
