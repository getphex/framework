<?php

declare(strict_types=1);

namespace Phex\Tests\Build\Fixtures;

final class NeedsRequest
{
    public function __construct(public \Phex\Http\Message\Request $request) {}
}
