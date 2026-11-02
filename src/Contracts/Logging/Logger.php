<?php

declare(strict_types=1);

namespace Phex\Contracts\Logging;

use Phex\Logging\Record;

interface Logger
{
    /** Explicit fields, without object serialization or message interpolation. */
    public function log(Record $record): void;
}
