<?php

declare(strict_types=1);

use Phex\Build\Config\NativeLifecycle;
use Phex\Build\Config\NativeServerConfig;

return new NativeServerConfig(
    lifecycle: NativeLifecycle::Request,
    listen: '127.0.0.1:8080',
    workers: 4,
    maxRequests: 1000,
);
