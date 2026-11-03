<?php

/**
 * Decodes and validates JSON user names before repository mutations in the CRUD example.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\Database;

use Phex\Http\Input\InputError;
use Phex\Http\Json\JsonObject;

final readonly class UserInput
{
    public function __construct(public string $name) {}

    public static function decode(string $body): UserInput
    {
        $name = JsonObject::decode($body)->get('name');
        if (!is_string($name) || trim($name) === '' || strlen($name) > 120) {
            throw InputError::validation(['name' => 'Expected a nonempty string of at most 120 bytes.']);
        }

        return new UserInput(trim($name));
    }
}
