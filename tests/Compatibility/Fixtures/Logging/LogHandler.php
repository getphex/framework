<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures\Logging;

use Phex\Contracts\Http\Handler;
use Phex\Contracts\Logging\Logger;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use Phex\Logging\Level;
use Phex\Logging\Record;
use RuntimeException;

final readonly class LogHandler implements Handler
{
    public function __construct(private Logger $logger) {}

    public function handle(Request $request): Response
    {
        $this->logger->log(new Record(Level::Notice, 'application.event', ['request_id' => 'spoofed', 'method' => 'spoofed']));
        if ($request->uri === '/fail') {
            throw new RuntimeException('private application failure');
        }

        return new Response(body: 'ok');
    }
}
