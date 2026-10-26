<?php

declare(strict_types=1);

namespace Phex\Contracts\Http;

use Phex\Http\Message\Request;
use Phex\Http\Message\Response;

interface Handler
{
    public function handle(Request $request): Response;
}
