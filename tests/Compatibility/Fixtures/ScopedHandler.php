<?php

/**
 * Checks that its owned resource stays open while returning controlled HTTP success or
 * failure.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Fiber;
use LogicException;
use Phex\Contracts\Http\Handler;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Throwable;

final readonly class ScopedHandler implements Handler
{
    public function __construct(
        private TrackedResource $resource,
        private ScopeLog $log,
        private ?Throwable $failure = null,
        private bool $suspend = false,
    ) {}

    public function handle(Request $request): Response
    {
        if ($this->resource->closes !== 0) {
            throw new LogicException('The resource was closed before the handler completed.');
        }

        $this->log->record($this->resource->name.':handle');

        if ($this->suspend) {
            Fiber::suspend($this->resource->name);
        }

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return new Response(200, new Headers(['X-Resource' => [$this->resource->name]]), $request->body);
    }
}
