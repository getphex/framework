<?php

/**
 * Sends status and repeated header fields through the shared SAPI header emission path.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Http;

use Phex\Http\Message\Headers;

/** @internal Response control supported by PHP SAPI and ElephC's web bridge. */
final class SapiHeaders
{
    public static function send(int $status, Headers $headers): void
    {
        foreach ($headers->all() as $name => $values) {
            foreach ($values as $value) {
                header((string) $name.': '.$value, false);
            }
        }
        // Location can change the status implicitly on both runtimes.
        http_response_code($status);
    }
}
