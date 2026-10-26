<?php

declare(strict_types=1);

namespace Phex\Kernel\Scope;

use Throwable;

/** @internal */
final class CleanupErrors
{
    // Declared storage retains the cause's type across catch boundaries.
    private ?Throwable $firstFailure = null;

    /** @var list<Throwable> */
    private array $additionalFailures = [];

    public function add(Throwable $error): void
    {
        if ($this->firstFailure === null) {
            $this->firstFailure = $error;
        } else {
            $this->additionalFailures[] = $error;
        }
    }

    public function throwIfAny(?Throwable $requestFailure): void
    {
        $first = $this->firstFailure;

        if ($first !== null) {
            throw new CleanupFailed($first, $this->additionalFailures, $requestFailure);
        }
    }
}
