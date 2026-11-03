<?php

/**
 * Returns fixed text for route precedence, method selection and named-route test fixtures.
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

final readonly class TextEndpoint implements Endpoint
{
    public function __construct(private string $text) {}

    public function handle(Request $_request, Parameters $_parameters): Response
    {
        return new Response(body: $this->text);
    }
}
