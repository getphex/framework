<?php

/**
 * Adapts ElephC worker lifecycle hooks to a retained framework application and shutdown
 * cleanup.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Elephc;

use Elephc\Web\Request;
use Elephc\Web\WorkerApplication;
use Phex\Contracts\Container\ApplicationContainer;
use Phex\Runtime\Http\PersistentWebApplication;
use Phex\Runtime\Http\RandomRequestIdGenerator;

final readonly class NativeApplication implements WorkerApplication
{
    private PersistentWebApplication $application;

    public function __construct(ApplicationContainer $container)
    {
        $this->application = new PersistentWebApplication($container, new NativeErrorReporter(), new RandomRequestIdGenerator());
    }

    public function handle(Request $request): void
    {
        $this->application->serve(new ApplicationRequestReader($request), new NativeOutput(), $request->method);
    }

    public function shutdown(): void
    {
        $this->application->close();
    }
}
