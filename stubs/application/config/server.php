<?php

/**
 * Selects the native lifecycle and supplies build-time defaults for server startup
 * options.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Phex\Build\Config\NativeLifecycle;
use Phex\Build\Config\NativeServerConfig;

return new NativeServerConfig(
    lifecycle: NativeLifecycle::Request,
    listen: '127.0.0.1:3131',
    workers: 4,
    maxRequests: 1000,
);
