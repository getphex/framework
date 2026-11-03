<?php

/**
 * Defines a generic contract for decoding an input string into a typed application object.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Contracts\Http;

/** @template-covariant T of object */
interface Decoder
{
    /** @return T */
    public function decode(string $input): object;
}
