<?php

/**
 * Enriches structured application log records with the current request's correlation
 * context.
 *
 * @package Phex
 * @link https://github.com/getphex/framework
 * @license MIT
 * @copyright Vincenzo Petrucci (Illegal Studio)
 */

declare(strict_types=1);

namespace Phex\Http\Logging;

use Phex\Contracts\Logging\Logger;
use Phex\Logging\JsonLogger;
use Phex\Logging\Record;

final readonly class RequestLogger implements Logger
{
    public function __construct(private JsonLogger $logger, private RequestLogContext $context) {}

    public function log(Record $record): void
    {
        $context = $record->context;
        $context['request_id'] = $this->context->id->value;
        $context['method'] = $this->context->method;
        $this->logger->log(new Record($record->level, $record->message, $context));
    }
}
