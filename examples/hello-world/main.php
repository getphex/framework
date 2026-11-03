<?php

/**
 * Runs the minimal greeting pipeline and prints its response as a shared PHP/native
 * example.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

use Phex\Examples\HelloWorld\GreetingHandler;
use Phex\Examples\HelloWorld\JsonMiddleware;
use Phex\Http\Message\Request;
use Phex\Http\Middleware\Pipeline;

require __DIR__.'/bootstrap.php';

$application = Pipeline::wrap(new JsonMiddleware(), new GreetingHandler());
$response = $application->handle(new Request('GET', '/hello'));

echo $response->body, "\n";
