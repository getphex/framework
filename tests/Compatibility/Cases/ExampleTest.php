<?php

/**
 * Verifies that the portable hello-world pipeline returns the expected greeting across
 * repeated requests.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use Phex\Examples\HelloWorld\GreetingHandler;
use Phex\Examples\HelloWorld\JsonMiddleware;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Middleware\Pipeline;
use PHPUnit\Framework\TestCase;

final class ExampleTest extends TestCase
{
    public function testTheExampleReturnsJsonThroughMiddleware(): void
    {
        $application = Pipeline::wrap(new JsonMiddleware(), new GreetingHandler());
        $response = $application->handle(new Request('GET', '/hello'));

        $this->assertSame(200, $response->status);
        $this->assertSame(['application/json'], $response->headers->values('content-type'));
        $this->assertSame('{"hello":"world"}', $response->body);
    }

    public function testTheSameExampleCanServeDifferentUsers(): void
    {
        $application = Pipeline::wrap(new JsonMiddleware(), new GreetingHandler());
        $first = $application->handle(new Request('GET', '/hello', new Headers(['X-Name' => ['alice']])));
        $second = $application->handle(new Request('GET', '/hello', new Headers(['X-Name' => ['bob']])));

        $this->assertSame('{"hello":"alice"}', $first->body);
        $this->assertSame('{"hello":"bob"}', $second->body);
    }
}
