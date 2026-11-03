<?php

/**
 * Marks invalid transport metadata that the HTTP host maps to a bad-request response.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Http;

use InvalidArgumentException;

final class InvalidRequest extends InvalidArgumentException {}
