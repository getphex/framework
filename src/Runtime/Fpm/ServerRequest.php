<?php

/**
 * Validates CGI-style server metadata and maps it to request methods, raw targets and HTTP
 * headers.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Runtime\Fpm;

use InvalidArgumentException;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Runtime\Http\InvalidRequest;

/** Convert CGI/SAPI metadata without accessing process globals. */
final class ServerRequest
{
    /** @param array<int|string, mixed> $server */
    public static function create(array $server, string $body): Request
    {
        $method = self::field($server, 'REQUEST_METHOD');
        $uri = self::field($server, 'REQUEST_URI');
        $fields = [];
        foreach ($server as $name => $value) {
            if (is_string($name) && str_starts_with($name, 'HTTP_')) {
                if (!is_string($value)) {
                    throw new InvalidRequest('HTTP header metadata must contain strings.');
                }
                // SAPI may already combine repeated fields. Do not split comma-containing values.
                $fields[str_replace('_', '-', substr($name, 5))] = [$value];
            }
        }
        foreach (['CONTENT_TYPE', 'CONTENT_LENGTH'] as $name) {
            if (array_key_exists($name, $server)) {
                $fields[str_replace('_', '-', $name)] = [self::field($server, $name)];
            }
        }
        try {
            return new Request($method, $uri, new Headers($fields), $body);
        } catch (InvalidArgumentException $error) {
            throw new InvalidRequest('Invalid HTTP request metadata.', previous: $error);
        }
    }

    /** @param array<int|string, mixed> $server */
    private static function field(array $server, string $name): string
    {
        $value = $server[$name] ?? null;
        if (!is_string($value)) {
            throw new InvalidRequest('Missing or invalid request field: '.$name);
        }

        return $value;
    }
}
