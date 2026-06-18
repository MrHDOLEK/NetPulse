<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;

use function array_map;
use function implode;
use function is_bool;
use function is_scalar;
use function json_encode;
use function preg_match;
use function str_replace;

final class LogfmtFormatter implements FormatterInterface
{
    public function format(LogRecord $record): string
    {
        $pairs = [
            'level=' . $record->level->toPsrLogLevel(),
            'msg=' . $this->quote($record->message),
            'channel=' . $this->quote($record->channel),
        ];

        foreach ($record->context as $key => $value) {
            $pairs[] = $this->quote((string) $key) . '=' . $this->encode($value);
        }

        return implode(' ', $pairs) . "\n";
    }

    /**
     * @param array<LogRecord> $records
     */
    public function formatBatch(array $records): string
    {
        return implode('', array_map(fn(LogRecord $record): string => $this->format($record), $records));
    }

    private function encode(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_scalar($value)) {
            return $this->quote((string) $value);
        }

        return $this->quote((string) json_encode($value));
    }

    private function quote(string $value): string
    {
        if ($value === '' || preg_match('/[\s="]/', $value) === 1) {
            return '"' . str_replace(["\\", '"'], ["\\\\", '\\"'], $value) . '"';
        }

        return $value;
    }
}
