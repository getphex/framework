<?php

declare(strict_types=1);

final class NativeLifecycleApplication
{
    public static int $constructed = 0;
    private static ?NativeLifecycleApplication $application = null;
    private int $requests = 0;

    public function __construct()
    {
        self::$constructed++;
    }

    public static function instance(): NativeLifecycleApplication
    {
        $cached = self::$application;
        if ($cached !== null) {
            return $cached;
        }
        $created = new NativeLifecycleApplication();
        self::$application = $created;

        return $created;
    }

    public function next(): int
    {
        return ++$this->requests;
    }
}

function phexLifecycleFunctionCounter(): int
{
    static $calls = 0;
    if (!is_int($calls)) {
        throw new LogicException('The static lifecycle counter must be an integer.');
    }

    return ++$calls;
}

global $phexLifecycleGlobal, $phexLifecyclePreload;
if (!isset($phexLifecycleGlobal)) {
    $phexLifecycleGlobal = 0;
}
if (!is_int($phexLifecycleGlobal)) {
    throw new LogicException('The lifecycle counter must be an integer.');
}
$phexLifecycleGlobal++;
$application = NativeLifecycleApplication::instance();
$identity = $_SERVER['HTTP_X_REQUEST_ID'] ?? '';
if (!is_string($identity)) {
    throw new LogicException('The lifecycle request ID must be a string.');
}
header('Content-Type: text/plain; charset=utf-8');
// Text output keeps the lifecycle probe independent of native JSON bridge ownership.
echo getmypid(), '|', $phexLifecycleGlobal, '|',
    phexLifecycleFunctionCounter(), '|', phexLifecycleFunctionCounter(), '|',
    NativeLifecycleApplication::$constructed, '|', $application->next(), '|',
    NativeLifecycleApplication::instance()->next(), '|', $identity, '|',
    ($phexLifecyclePreload ?? null) === null ? 'unset' : 'retained';
