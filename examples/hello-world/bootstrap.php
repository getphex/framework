<?php

/**
 * Loads explicit shared declarations for the hello-world example on PHP and ElephC.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

require_once __DIR__.'/../../src/Contracts/Http/Handler.php';
require_once __DIR__.'/../../src/Contracts/Http/Middleware.php';
require_once __DIR__.'/../../src/Http/Message/Token.php';
require_once __DIR__.'/../../src/Http/Message/Headers.php';
require_once __DIR__.'/../../src/Http/Message/Request.php';
require_once __DIR__.'/../../src/Http/Message/Response.php';
require_once __DIR__.'/../../src/Http/Middleware/Pipeline.php';
require_once __DIR__.'/GreetingHandler.php';
require_once __DIR__.'/JsonMiddleware.php';
