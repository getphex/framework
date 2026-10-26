<?php

declare(strict_types=1);

namespace Phex\Kernel;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Kernel\HandlerFactory;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Kernel\Scope\RequestScope;
use Throwable;

final readonly class Application implements Handler
{
    public function __construct(private HandlerFactory $factory) {}

    public function handle(Request $request): Response
    {
        $scope = new RequestScope($request);
        $failure = null;

        try {
            $handler = $this->factory->create($scope);

            return $handler->handle($request);
        } catch (Throwable $error) {
            $failure = $error;
            throw $error;
        } finally {
            $scope->close($failure);
        }
    }
}
