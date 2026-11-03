<?php

/**
 * Exercises successful JSON routes, missing paths and rejected methods from the command
 * line.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Phex\Examples\JsonApi\JsonApi;
use Phex\Http\Message\Request;

require __DIR__.'/bootstrap.php';

$application = JsonApi::application();

foreach ([
    new Request('GET', '/health'),
    new Request('GET', '/users/42'),
    new Request('GET', '/missing'),
    new Request('POST', '/users/42'),
] as $request) {
    $response = $application->handle($request);
    echo $response->status, ' ', $response->body, "\n";
}
