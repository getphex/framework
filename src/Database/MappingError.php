<?php

/**
 * Signals missing columns or incompatible scalar types during explicit database row
 * mapping.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Database;

use RuntimeException;

final class MappingError extends RuntimeException {}
