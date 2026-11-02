<?php

declare(strict_types=1);

namespace Phex\Database;

use RuntimeException;

/** Public messages exclude SQL, parameters, DSNs and credentials. */
final class DatabaseError extends RuntimeException {}
