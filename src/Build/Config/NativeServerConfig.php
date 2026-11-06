<?php

/**
 * Validates build-host configuration for native lifecycle, listening address, worker count
 * and recycling.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Build\Config;

use InvalidArgumentException;

/** Build-host configuration. Startup defaults do not enter the native PHP graph. */
final readonly class NativeServerConfig
{
    public function __construct(
        public NativeLifecycle $lifecycle = NativeLifecycle::Request,
        public string $listen = '127.0.0.1:3131',
        public int $workers = 4,
        public int $maxRequests = 1000,
    ) {
        if ($workers < 1 || $maxRequests < 0) {
            throw new InvalidArgumentException('Native workers must be positive and maxRequests must be nonnegative.');
        }
        $separator = strrpos($listen, ':');
        if ($separator === false || $separator === 0 || preg_match('/[\x00-\x20\x7f]/', $listen) === 1) {
            throw new InvalidArgumentException('Native listen must contain an address and port.');
        }
        $port = substr($listen, $separator + 1);
        if ($port === '' || preg_match('/^[0-9]+$/D', $port) !== 1 || strlen($port) > 5 || (int) $port < 1 || (int) $port > 65535) {
            throw new InvalidArgumentException('Native listen port must be between 1 and 65535.');
        }
    }

    public static function fromFile(string $path): NativeServerConfig
    {
        $config = require $path;
        if (!$config instanceof NativeServerConfig) {
            throw new InvalidArgumentException('Native server configuration must return NativeServerConfig: '.$path);
        }

        return $config;
    }
}
