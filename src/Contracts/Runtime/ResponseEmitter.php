<?php

declare(strict_types=1);

namespace Phex\Contracts\Runtime;

use Phex\Http\Message\Response;

interface ResponseEmitter
{
    public function emit(Response $response, string $requestMethod): void;
}
