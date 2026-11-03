<?php

/**
 * Records master preload startup and initializes probe state for native worker recycling
 * checks.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

global $phexLifecyclePreload;
$phexLifecyclePreload = 41;
$events = getenv('PHEX_LIFECYCLE_EVENTS');
if ($events !== false && $events !== '') {
    file_put_contents($events, (string) getmypid()."\n", FILE_APPEND);
}
echo "phex:preload-startup\n";
