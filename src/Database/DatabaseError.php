<?php

/**
 * Reports database operation failures through safe public messages while retaining their
 * original causes.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Database;

use RuntimeException;

/** Public messages exclude SQL, parameters, DSNs and credentials. */
final class DatabaseError extends RuntimeException {}
