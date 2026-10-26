<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Fixtures;

use Phex\Contracts\Http\Handler;
use Phex\Http\Message\Request;
use Phex\Http\Message\Response;
use RuntimeException;

final class FailOnceHandler implements Handler
{
    private bool $failed = false;

    public function handle(Request $request): Response
    {
        if (!$this->failed) {
            $this->failed = true;
            throw new RuntimeException('handler failed');
        }

        return new Response(body: $request->body);
    }
}
