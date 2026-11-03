<?php

/**
 * Verifies HTTP host output, application cleanup and safe error reporting across transport
 * failures.
 *
 * @package Phex
 * @link https://github.com/getphex/phex
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use LogicException;
use Phex\Examples\CompiledContainer\Generated\ApplicationContainer;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Runtime\Http\WebApplication;
use Phex\Tests\Compatibility\Fixtures\HttpOutput;
use Phex\Tests\Compatibility\Fixtures\RequestInput;
use Phex\Tests\Compatibility\Fixtures\Errors;
use PHPUnit\Framework\TestCase;

final class WebApplicationTest extends TestCase
{
    public function testTheSharedHostPreservesJsonAndClosesApplicationResources(): void
    {
        $container = new ApplicationContainer();
        $output = new HttpOutput();
        $request = new Request('POST', '/users', new Headers(['Content-Type' => ['application/json']]), '{"name":"Ada"}');
        WebApplication::serve(new RequestInput($request), $output, fn (): ApplicationContainer => $container, new Errors());
        $this->assertSame(201, $output->status);
        $this->assertSame('{"id":1,"name":"Ada"}', $output->body);
        $this->assertSame(['application/json'], $output->headers()->values('Content-Type'));
        $this->assertSame(['/users/1'], $output->headers()->values('Location'));
        $this->expectException(LogicException::class);
        $container->repository();
    }

    public function testTransportFailureClosesTheContainerAndHonorsHeadSuppression(): void
    {
        $container = new ApplicationContainer();
        $output = new HttpOutput();
        WebApplication::serve(new RequestInput(new Request('HEAD', '/'), true), $output, fn (): ApplicationContainer => $container, new Errors(), 'HEAD');
        $this->assertSame(400, $output->status);
        $this->assertSame('', $output->body);
        $this->assertSame(['text/plain; charset=utf-8'], $output->headers()->values('Content-Type'));
        $this->expectException(LogicException::class);
        $container->repository();
    }

    public function testUnexpectedFailuresAreReportedAndDoNotExposePrivateDetails(): void
    {
        $container = new ApplicationContainer();
        $output = new HttpOutput();
        $errors = new Errors();
        WebApplication::serve(new RequestInput(new Request('GET', '/'), false, true), $output, fn (): ApplicationContainer => $container, $errors);
        $this->assertSame(500, $output->status);
        $this->assertSame('Internal Server Error', $output->body);
        $this->assertSame('private failure', $errors->message);
        $this->expectException(LogicException::class);
        $container->repository();
    }
}
