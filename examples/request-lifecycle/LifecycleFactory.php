<?php

declare(strict_types=1);

namespace Phex\Examples\RequestLifecycle;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Kernel\HandlerFactory;
use Phex\Http\Middleware\Pipeline;
use Phex\Kernel\Scope\RequestScope;

final readonly class LifecycleFactory implements HandlerFactory
{
    public function __construct(private Handler $sharedRouter) {}

    public function create(RequestScope $scope): Handler
    {
        $ids = $scope->request->headers->values('X-Request-Id');
        $trace = $scope->own(new RequestTrace($ids[0] ?? 'anonymous'));

        return Pipeline::wrap(new ScopeMiddleware($trace), $this->sharedRouter);
    }
}
