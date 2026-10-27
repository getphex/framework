<?php

declare(strict_types=1);

global $phexLifecyclePreload;
$phexLifecyclePreload = 41;
$events = getenv('PHEX_LIFECYCLE_EVENTS');
if ($events !== false && $events !== '') {
    file_put_contents($events, (string) getmypid()."\n", FILE_APPEND);
}
echo "phex:preload-startup\n";
