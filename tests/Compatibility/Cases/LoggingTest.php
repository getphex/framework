<?php

declare(strict_types=1);

namespace Phex\Tests\Compatibility\Cases;

use InvalidArgumentException;
use JsonException;
use LogicException;
use Phex\Http\Logging\RequestId;
use Phex\Http\Logging\RequestLogContext;
use Phex\Http\Logging\RequestLoggingMiddleware;
use Phex\Http\Message\Headers;
use Phex\Http\Message\Request;
use Phex\Http\Middleware\Pipeline;
use Phex\Kernel\Scope\RequestScope;
use Phex\Logging\JsonLogger;
use Phex\Logging\Level;
use Phex\Logging\Record;
use Phex\Logging\LoggerErrorReporter;
use Phex\Runtime\Http\RandomRequestIdGenerator;
use Phex\Runtime\Http\WebApplication;
use Phex\Tests\Compatibility\Fixtures\Errors;
use Phex\Tests\Compatibility\Fixtures\HttpOutput;
use Phex\Tests\Compatibility\Fixtures\Logging\MemoryLogs;
use Phex\Tests\Compatibility\Fixtures\Logging\SequentialRequestIds;
use Phex\Tests\Compatibility\Fixtures\RequestInput;
use Phex\Tests\Compatibility\Fixtures\EchoHandler;
use Phex\Tests\Compatibility\Fixtures\FailOnceHandler;
use Phex\Tests\Generated\LoggingContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LoggingTest extends TestCase
{
    /** @return list<array{string, int}> */
    public static function levels(): array
    {
        return [['debug', 0], ['info', 1], ['notice', 2], ['warning', 3],
            ['error', 4], ['critical', 5], ['alert', 6], ['emergency', 7]];
    }

    #[DataProvider('levels')]
    public function testEveryLevelHasAStableJsonLabel(string $level, int $severity): void
    {
        $output = new MemoryLogs();
        $logger = new JsonLogger($output);
        $severityLevel = Level::from($level);
        $event = new Record($severityLevel, 'an event');
        $logger->log($event);
        $record = $output->record(0);
        $this->assertSame($level, $record->get('level'));
        $this->assertSame($severity, Level::severity($level));
        $this->assertSame('an event', $record->get('message'));
        $this->assertTrue(is_string($record->get('timestamp')));
        // Pass a primitive count across the native assertion boundary.
        $fieldCount = count($record->all());
        $this->assertSame(3, $fieldCount);
    }

    public function testContextKeepsScalarTypesAndMetadataCannotBeOverwritten(): void
    {
        $output = new MemoryLogs();
        (new JsonLogger($output))->log(new Record(Level::Info, 'user {id}', [
            'id' => 7, 'active' => true, 'ratio' => 1.5, 'optional' => null, 'name' => 'Ada',
            'timestamp' => 'forged', 'level' => 'forged', 'message' => 'forged',
        ]));
        $record = $output->record(0);
        $this->assertSame(7, $record->get('id'));
        $this->assertTrue($record->get('active'));
        $this->assertSame(1.5, $record->get('ratio'));
        $this->assertNull($record->get('optional'));
        $this->assertSame('Ada', $record->get('name'));
        $this->assertSame('info', $record->get('level'));
        $this->assertSame('user {id}', $record->get('message'));
        $timestamp = $record->get('timestamp');
        if (!is_string($timestamp)) {
            throw new LogicException('The log timestamp must be a string.');
        }
        $this->assertTrue(str_ends_with($timestamp, 'Z'));
    }

    public function testLogMessagesCannotInjectNewRecordsAndInvalidUtf8IsReplaced(): void
    {
        $output = new MemoryLogs();
        (new JsonLogger($output))->log(new Record(Level::Info, "line one\nline two\r\0\xff", ['binary' => "\xff"]));
        $this->assertCount(1, $output->records);
        $this->assertFalse(str_contains($output->records[0], "\n"));
        $this->assertFalse(str_contains($output->records[0], "\r"));
        $this->assertSame("line one\nline two\r\0\xef\xbf\xbd", $output->record(0)->get('message'));
        $this->assertSame("\xef\xbf\xbd", $output->record(0)->get('binary'));
    }

    public function testFilteringHappensBeforeEncodingOrWriting(): void
    {
        $output = new MemoryLogs();
        $logger = new JsonLogger($output, Level::Warning);
        $logger->log(new Record(Level::Debug, 'ignored', ['invalid' => INF]));
        $logger->log(new Record(Level::Info, 'ignored'));
        $logger->log(new Record(Level::Notice, 'ignored'));
        $logger->log(new Record(Level::Warning, 'threshold'));
        $logger->log(new Record(Level::Emergency, 'urgent'));
        $this->assertCount(2, $output->records);
        $this->assertSame('warning', $output->record(0)->get('level'));
        $this->assertSame('emergency', $output->record(1)->get('level'));
    }

    public function testUnencodableContextFailsBeforeWritingAPartialRecord(): void
    {
        $output = new MemoryLogs();
        try {
            (new JsonLogger($output))->log(new Record(Level::Error, 'invalid', ['infinite' => INF]));
        } catch (JsonException) {
            $this->assertSame([], $output->records);

            return;
        }
        $this->fail('Non-finite context must not produce invalid JSON.');
    }

    public function testErrorReporterUsesExplicitFieldsWithoutSerializingTraces(): void
    {
        $output = new MemoryLogs();
        $logger = new JsonLogger($output);
        $reporter = new LoggerErrorReporter($logger);
        $reporter->report(new RuntimeException('private failure'),
            ['request_id' => 'request-1', 'exception_type' => 'forged']);
        $record = $output->record(0);
        $this->assertSame('error', $record->get('level'));
        $this->assertSame('private failure', $record->get('message'));
        $this->assertSame(RuntimeException::class, $record->get('exception_type'));
        $this->assertSame('request-1', $record->get('request_id'));
        $fieldCount = count($record->all());
        $this->assertSame(5, $fieldCount);
    }

    /** @return list<array{string}> */
    public static function invalidIds(): array
    {
        return [[''], [str_repeat('a', 129)], ['a b'], ['a.b'], ['a/b'], ["a\r\nb"], ["a\0b"], ["caf\u{00e9}"]];
    }

    #[DataProvider('invalidIds')]
    public function testRequestIdsAreBoundedHeaderSafeTokens(string $value): void
    {
        $this->expectException(InvalidArgumentException::class);
        new RequestId($value);
    }

    public function testIdsAcceptTheDocumentedAlphabetAndMaximumLength(): void
    {
        $this->assertSame('Az09_-', (new RequestId('Az09_-'))->value);
        $this->assertSame(str_repeat('a', 128), (new RequestId(str_repeat('a', 128)))->value);
    }

    public function testTheDefaultGeneratorProducesFreshLowercaseHexIds(): void
    {
        $generator = new RandomRequestIdGenerator();
        $first = $generator->generate()->value;
        $second = $generator->generate()->value;
        $this->assertSame(32, strlen($first));
        $hexadecimal = true;
        for ($index = 0; $index < strlen($first); $index++) {
            if (!str_contains('0123456789abcdef', $first[$index])) {
                $hexadecimal = false;
            }
        }
        $this->assertTrue($hexadecimal);
        $this->assertFalse($first === $second);
    }

    public function testRequestContextRequiresTheHostsSingleId(): void
    {
        $this->expectException(LogicException::class);
        RequestLogContext::create(new Request('GET', '/'));
    }

    public function testRepeatedRequestIdsCannotBecomeAnAmbiguousLogContext(): void
    {
        $this->expectException(LogicException::class);
        RequestLogContext::create(new Request('GET', '/', new Headers(['X-Request-Id' => ['first', 'second']])));
    }

    public function testOpenRequestScopesKeepTheirLogContextsSeparate(): void
    {
        $container = new LoggingContainer();
        $first = new RequestScope(new Request('GET', '/?secret=do-not-log', new Headers(['X-Request-Id' => ['first']]), 'private body'));
        $second = new RequestScope(new Request('POST', '/', new Headers(['X-Request-Id' => ['second']])));
        try {
            $one = $container->request($first);
            $two = $container->request($second);
            $firstLogger = $one->logger();
            $secondLogger = $two->logger();
            $this->assertSame($firstLogger, $one->logger());
            $this->assertFalse($firstLogger === $secondLogger);
            $secondLogger->log(new Record(Level::Info, 'second'));
            $firstLogger->log(new Record(Level::Info, 'first', ['request_id' => 'forged', 'method' => 'forged']));
            $logs = self::logs($container->logs());
            $this->assertSame('second', $logs->record(0)->get('request_id'));
            $this->assertSame('POST', $logs->record(0)->get('method'));
            $this->assertSame('first', $logs->record(1)->get('request_id'));
            $this->assertSame('GET', $logs->record(1)->get('method'));
            $fieldCount = count($logs->record(1)->all());
            $this->assertSame(5, $fieldCount);
            $this->assertFalse(str_contains(implode('', $logs->records), 'private'));
            $this->assertFalse(str_contains(implode('', $logs->records), 'do-not-log'));
            $first->close();
            $secondLogger->log(new Record(Level::Info, 'still second'));
            $this->assertSame('second', $logs->record(2)->get('request_id'));
        } finally {
            $first->close();
            $second->close();
            $container->close();
        }
    }

    public function testHostCorrelationReplacesClientIdsAndMatchesApplicationLogs(): void
    {
        $container = new LoggingContainer();
        $logs = self::logs($container->logs());
        $output = new HttpOutput();
        $request = new Request('POST', '/?secret=do-not-log', new Headers(['X-Request-Id' => ['client', 'duplicate']]));
        WebApplication::serve(new RequestInput($request), $output, fn (): LoggingContainer => $container, new Errors(), 'GET', new SequentialRequestIds());
        $this->assertSame(200, $output->status);
        $this->assertSame(['request-1'], $output->headers()->values('X-Request-Id'));
        $this->assertSame('request-1', $logs->record(0)->get('request_id'));
        $this->assertSame('POST', $logs->record(0)->get('method'));
        $this->assertFalse(str_contains($logs->records[0], 'do-not-log'));
    }

    public function testUnhandledFailureIsReportedOnceWithTheResponseId(): void
    {
        $container = new LoggingContainer();
        $logs = self::logs($container->logs());
        $output = new HttpOutput();
        WebApplication::serve(new RequestInput(new Request('GET', '/fail')), $output, fn (): LoggingContainer => $container,
            new LoggerErrorReporter(new JsonLogger($logs)), 'GET', new SequentialRequestIds());
        $this->assertSame(500, $output->status);
        $this->assertSame('Internal Server Error', $output->body);
        $this->assertSame(['request-1'], $output->headers()->values('X-Request-Id'));
        $this->assertCount(2, $logs->records);
        $this->assertSame('notice', $logs->record(0)->get('level'));
        $this->assertSame('error', $logs->record(1)->get('level'));
        $this->assertSame('private application failure', $logs->record(1)->get('message'));
        $this->assertSame('request-1', $logs->record(1)->get('request_id'));
    }

    public function testBootstrapFailuresAndHeadErrorsStillCarryCorrelation(): void
    {
        $output = new HttpOutput();
        $errors = new Errors();
        WebApplication::serve(new RequestInput(new Request('HEAD', '/')), $output, function (): LoggingContainer {
            throw new RuntimeException('private bootstrap failure');
        }, $errors, 'HEAD', new SequentialRequestIds());
        $this->assertSame(500, $output->status);
        $this->assertSame('', $output->body);
        $this->assertSame(['request-1'], $output->headers()->values('X-Request-Id'));
        $this->assertSame(['request_id' => 'request-1'], $errors->context);
    }

    public function testRejectedTransportStillGetsAnIdWithoutReportingAnApplicationError(): void
    {
        $output = new HttpOutput();
        $errors = new Errors();
        WebApplication::serve(new RequestInput(new Request('GET', '/'), true), $output, fn (): LoggingContainer => new LoggingContainer(),
            $errors, 'GET', new SequentialRequestIds());
        $this->assertSame(400, $output->status);
        $this->assertSame(['request-1'], $output->headers()->values('X-Request-Id'));
        $this->assertSame('', $errors->message);
    }

    public function testCompletionMiddlewarePreservesTheResponseAndLogsItsStatus(): void
    {
        $logs = new MemoryLogs();
        $pipeline = Pipeline::wrap(new RequestLoggingMiddleware(new JsonLogger($logs)), new EchoHandler());
        $response = $pipeline->handle(new Request('POST', '/', new Headers(['X-Request' => ['test'], 'X-Trace' => ['test']]), 'body'));
        $this->assertSame('body', $response->body);
        $this->assertSame('http.request.completed', $logs->record(0)->get('message'));
        $this->assertSame(200, $logs->record(0)->get('status'));
    }

    public function testCompletionMiddlewarePreservesFailuresAndRecoversOnTheNextCall(): void
    {
        $logs = new MemoryLogs();
        $pipeline = Pipeline::wrap(new RequestLoggingMiddleware(new JsonLogger($logs)), new FailOnceHandler());
        try {
            $pipeline->handle(new Request('GET', '/'));
            $this->fail('The original handler failure must propagate.');
        } catch (RuntimeException $error) {
            $this->assertSame('handler failed', $error->getMessage());
        }
        $this->assertSame([], $logs->records);
        $pipeline->handle(new Request('GET', '/'));
        $this->assertSame(1, $logs->count());
    }

    public function testOneImmutableRecordCanBeUsedByTwoRequestLoggers(): void
    {
        $logs = new MemoryLogs();
        $output = new JsonLogger($logs);
        $one = new \Phex\Http\Logging\RequestLogger($output, new RequestLogContext(new RequestId('first'), 'GET'));
        $two = new \Phex\Http\Logging\RequestLogger($output, new RequestLogContext(new RequestId('second'), 'POST'));
        $record = new Record(Level::Notice, 'shared event', ['id' => 7]);
        $one->log($record);
        $two->log($record);
        $this->assertSame(['id' => 7], $record->context);
        $this->assertSame('first', $logs->record(0)->get('request_id'));
        $this->assertSame('second', $logs->record(1)->get('request_id'));
        $this->assertSame(7, $logs->record(0)->get('id'));
        $this->assertSame(7, $logs->record(1)->get('id'));
    }

    private static function logs(mixed $value): MemoryLogs
    {
        if (!$value instanceof MemoryLogs) {
            throw new LogicException('The test needs an in-memory log output.');
        }

        return $value;
    }
}
