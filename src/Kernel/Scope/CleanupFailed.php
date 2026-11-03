<?php

/**
 * Preserves cleanup errors together with an optional original request failure for reliable
 * diagnostics.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Kernel\Scope;

use RuntimeException;
use Throwable;

final class CleanupFailed extends RuntimeException
{
    /** @var non-empty-list<Throwable> */
    private readonly array $failures;

    /** @param list<Throwable> $additionalFailures */
    public function __construct(
        public readonly Throwable $firstFailure,
        array $additionalFailures = [],
        public readonly ?Throwable $requestFailure = null,
    ) {
        $failures = [$firstFailure];

        foreach ($additionalFailures as $failure) {
            $failures[] = $failure;
        }

        $this->failures = $failures;
        parent::__construct('Failed to release request resources.', 0, $requestFailure ?? $firstFailure);
    }

    /** @return non-empty-list<Throwable> */
    public function failures(): array
    {
        return $this->failures;
    }
}
