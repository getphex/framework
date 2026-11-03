<?php

/**
 * Maps an owned ElephC worker request into immutable framework input for application
 * lifecycle execution.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Elephc;

use Elephc\Web\Request as NativeRequest;
use Phex\Contracts\Runtime\RequestReader;
use Phex\Http\Message\Request;
use Phex\Runtime\Fpm\ServerRequest;

/** Uses the runtime-owned body rather than reading the HTTP stream a second time. */
final readonly class ApplicationRequestReader implements RequestReader
{
    public function __construct(private NativeRequest $request) {}

    public function read(): Request
    {
        $server = $_SERVER;
        $server['REQUEST_METHOD'] = $this->request->method;
        $server['REQUEST_URI'] = $this->request->uri;

        return ServerRequest::create($server, $this->request->body);
    }
}
