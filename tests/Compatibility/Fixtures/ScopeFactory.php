<?php

/**
 * Creates observable request resources and handlers, including controlled construction
 * failures.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use LogicException;
use Phex\Contracts\Http\Handler;
use Phex\Contracts\Kernel\HandlerFactory;
use Phex\Kernel\Scope\RequestScope;
use Throwable;

final class ScopeFactory implements HandlerFactory
{
    private ?RequestScope $lastScope = null;
    private ?TrackedResource $lastResource = null;

    public function __construct(
        private ScopeLog $log,
        private ?Throwable $factoryFailure = null,
        private ?Throwable $handlerFailure = null,
        private ?Throwable $cleanupFailure = null,
        private bool $suspend = false,
    ) {}

    public function create(RequestScope $scope): Handler
    {
        $name = $scope->request->body;
        $this->log->record($name.':create');
        $resource = $scope->own(new TrackedResource($name, $this->log, $this->cleanupFailure));
        $this->lastScope = $scope;
        $this->lastResource = $resource;

        if ($this->factoryFailure !== null) {
            throw $this->factoryFailure;
        }

        return new ScopedHandler($resource, $this->log, $this->handlerFailure, $this->suspend);
    }

    public function scope(): RequestScope
    {
        $scope = $this->lastScope;

        if ($scope === null) {
            throw new LogicException('No scope has been observed.');
        }

        return $scope;
    }

    public function resource(): TrackedResource
    {
        $resource = $this->lastResource;

        if ($resource === null) {
            throw new LogicException('No resource has been observed.');
        }

        return $resource;
    }
}
