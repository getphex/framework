<?php

/**
 * Reports unknown named routes or invalid URL parameters as application configuration
 * errors.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Routing\Url;

use InvalidArgumentException;

final class UrlGenerationFailed extends InvalidArgumentException {}
