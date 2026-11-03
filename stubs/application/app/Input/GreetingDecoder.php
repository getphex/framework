<?php

/**
 * Validates greeting JSON fields and returns a concrete input DTO without runtime
 * reflection.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace App\Input;

use Phex\Contracts\Http\Decoder;
use Phex\Http\Json\InputError;
use Phex\Http\Json\JsonObject;

/** @implements Decoder<GreetingInput> */
final class GreetingDecoder implements Decoder
{
    public function decode(string $input): GreetingInput
    {
        $name = JsonObject::decode($input)->get('name');
        if (!is_string($name) || trim($name) === '' || strlen($name) > 100) {
            throw InputError::validation(['name' => 'Expected a nonempty string of at most 100 UTF-8 bytes.']);
        }

        return new GreetingInput(trim($name));
    }
}
