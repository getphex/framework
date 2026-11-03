<?php

/**
 * Requires the current HTTP request to test reserved dependency availability and lifetime
 * constraints.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class NeedsRequest
{
    public function __construct(public \Phex\Http\Message\Request $request) {}
}
