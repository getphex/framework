<?php

/**
 * Signals invalid route parameter conversion so routing can return the appropriate client
 * error.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

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
