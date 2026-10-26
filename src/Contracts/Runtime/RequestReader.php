<?php

declare(strict_types=1);

namespace Phex\Contracts\Runtime;

use Phex\Http\Message\Request;

interface RequestReader
{
    public function read(): Request;
}
