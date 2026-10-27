<?php

declare(strict_types=1);

namespace Phex\Http\Query;

use Phex\Http\Message\Request;

final class QueryString
{
    public static function raw(Request $request): string
    {
        $start = strpos($request->uri, '?');

        return $start === false ? '' : substr($request->uri, $start + 1);
    }

    /**
     * @template T of object
     * @param callable(string): T $decoder
     * @return T
     */
    public static function decode(Request $request, callable $decoder): object
    {
        return $decoder(self::raw($request));
    }
}
