<?php

/**
 * Parses checked signed decimal integers without accepting overflow or implicit numeric
 * coercion.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Support;

/** Checked signed decimal conversion shared by route and query parameters. */
final class IntegerParser
{
    public static function parse(string $raw): ?int
    {
        if ($raw === '') {
            return null;
        }
        $negative = $raw[0] === '-';
        $start = $negative ? 1 : 0;
        if (strlen($raw) === $start) {
            return null;
        }
        for ($index = $start; $index < strlen($raw); $index++) {
            $byte = ord($raw[$index]);
            if ($byte < 48 || $byte > 57) {
                return null;
            }
        }
        $digits = ltrim(substr($raw, $start), '0');
        if ($digits === '') {
            return 0;
        }
        $limit = $negative ? substr((string) PHP_INT_MIN, 1) : (string) PHP_INT_MAX;
        if (strlen($digits) > strlen($limit)
            || (strlen($digits) === strlen($limit) && strcmp($digits, $limit) > 0)) {
            return null;
        }

        return (int) ($negative ? '-'.$digits : $digits);
    }
}
