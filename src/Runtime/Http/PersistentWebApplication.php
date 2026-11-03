<?php

/**
 * Retains one application container while dispatching isolated requests and closing
 * resources at shutdown.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Http;

use LogicException;
use Phex\Contracts\Container\ApplicationContainer;
use Phex\Contracts\Kernel\ScopeResource;
use Phex\Contracts\Runtime\ErrorReporter;
use Phex\Contracts\Runtime\RequestIdGenerator;
use Phex\Contracts\Runtime\RequestReader;
use Phex\Contracts\Runtime\ResponseOutput;

/** One owner for application state; dispatch always creates a fresh kernel request scope. */
final class PersistentWebApplication implements ScopeResource
{
    private bool $closed = false;

    public function __construct(
        private readonly ApplicationContainer $container,
        private readonly ErrorReporter $errors,
        private readonly ?RequestIdGenerator $requestIds = null,
    ) {}

    /**
     * @template TReader of RequestReader
     * @template TOutput of ResponseOutput
     * @param TReader $reader
     * @param TOutput $output
     */
    public function serve(RequestReader $reader, ResponseOutput $output, string $requestMethod = 'GET'): void
    {
        if ($this->closed) {
            throw new LogicException('The persistent application is closed.');
        }
        WebApplication::serveRetained($reader, $output, $this->container, $this->errors, $requestMethod, $this->requestIds);
    }

    public function close(): void
    {
        if (!$this->closed) {
            $this->closed = true;
            $this->container->close();
        }
    }
}
