<?php

/**
 * Validates JSON user-creation input and returns the example's typed request DTO.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Examples\CompiledContainer;

use Phex\Contracts\Http\Decoder;
use Phex\Http\Json\InputError;
use Phex\Http\Json\JsonObject;

/** @implements Decoder<CreateUserInput> */
final class CreateUserDecoder implements Decoder
{
    public function decode(string $input): CreateUserInput
    {
        $object = JsonObject::decode($input);
        $errors = [];
        foreach (array_keys($object->all()) as $key) {
            if ((string) $key !== 'name') {
                $errors['body'] = 'Unexpected field.';
            }
        }
        $raw = $object->get('name');
        if (!is_string($raw)) {
            $errors['name'] = 'Expected a string.';
        }
        $name = is_string($raw) ? trim($raw, ' ') : '';
        if (is_string($raw) && !self::validName($name)) {
            $errors['name'] = 'Expected 1 to 100 UTF-8 bytes without control characters.';
        }
        if ($errors !== []) {
            throw InputError::validation($errors);
        }

        return new CreateUserInput($name);
    }

    private static function validName(string $name): bool
    {
        if ($name === '' || strlen($name) > 100) {
            return false;
        }
        for ($index = 0; $index < strlen($name); $index++) {
            $byte = ord($name[$index]);
            if ($byte < 32 || $byte === 127) {
                return false;
            }
        }

        return true;
    }
}
