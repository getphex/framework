<?php

/**
 * Defines structured logging through explicit records without coupling callers to an
 * output transport.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Logging;

use Phex\Logging\Record;

interface Logger
{
    /** Explicit fields, without object serialization or message interpolation. */
    public function log(Record $record): void;
}
