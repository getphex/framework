<?php

declare(strict_types=1);

namespace Phex\Routing\Parameter;

use InvalidArgumentException;

final class InvalidParameter extends InvalidArgumentException
{
    public function __construct(string $name)
    {
        parent::__construct('Invalid route parameter: '.$name);
    }
}
