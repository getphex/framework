<?php

/**
 * Retains the JSON middleware entry point while sharing the common input-error response
 * policy.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Json;

use Phex\Http\Input\InputErrorMiddleware;

/** Compatibility name for the shared JSON input-error response policy. */
final class JsonInputMiddleware extends InputErrorMiddleware {}
