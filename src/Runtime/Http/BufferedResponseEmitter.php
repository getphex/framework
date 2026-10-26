<?php

declare(strict_types=1);

namespace Phex\Runtime\Http;

use Phex\Contracts\Runtime\ResponseEmitter;
use Phex\Contracts\Runtime\ResponseOutput;
use Phex\Http\Message\Response;

final readonly class BufferedResponseEmitter implements ResponseEmitter
{
    public function __construct(private ResponseOutput $output) {}

    public function emit(Response $response, string $requestMethod): void
    {
        // The output transport owns framing. This emitter only sends complete string bodies.
        $headers = $response->headers->without('Transfer-Encoding')->without('Trailer');
        $tunnel = $requestMethod === 'CONNECT' && $response->status >= 200 && $response->status < 300;
        $noContent = $response->status < 200 || $response->status === 204 || $response->status === 304
            || $tunnel;
        if ($noContent) {
            // A 304 may omit representation length; it cannot be inferred from its empty body.
            $headers = $headers->without('Content-Length');
        } elseif ($response->status === 205) {
            $headers = $headers->with('Content-Length', ['0']);
        } else {
            $headers = $headers->with('Content-Length', [(string) strlen($response->body)]);
        }
        $this->output->start($response->status, $headers);
        if (!$noContent && $response->status !== 205 && $requestMethod !== 'HEAD') {
            $this->output->write($response->body);
        }
    }
}
