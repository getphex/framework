<?php

declare(strict_types=1);

namespace Phex\Logging;

use Phex\Contracts\Logging\Logger;
use Phex\Contracts\Logging\LogOutput;

final readonly class JsonLogger implements Logger
{
    public function __construct(private LogOutput $output, private Level $minimum = Level::Debug) {}

    /** A static DI factory keeps the enum default out of generated constructor arguments. */
    public static function create(LogOutput $output): JsonLogger
    {
        return new JsonLogger($output);
    }

    public function log(Record $record): void
    {
        if (Level::severity($record->level->value) < Level::severity($this->minimum->value)) {
            return;
        }
        $fields = $record->context;
        $fields['timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
        $fields['level'] = $record->level->value;
        $fields['message'] = $record->message;
        $encoded = json_encode($fields, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        $this->output->write($encoded);
    }
}
