<?php

declare(strict_types=1);

namespace App\Metrics\Infrastructure\RemoteWrite;

use App\Metrics\Domain\RemoteWrite\Collection\TimeSeriesCollection;
use App\Metrics\Domain\RemoteWrite\ValueObject\Label;
use App\Metrics\Domain\RemoteWrite\ValueObject\Sample;
use App\Metrics\Domain\RemoteWrite\ValueObject\TimeSeries;

final class RemoteWriteEncoder
{
    public function encodeWriteRequest(TimeSeriesCollection $series): string
    {
        $out = "";

        foreach ($series as $timeSeries) {
            $out .= $this->lengthDelimited(1, $this->encodeTimeSeries($timeSeries));
        }

        return $out;
    }

    public function snappy(string $payload): string
    {
        if (function_exists("snappy_compress")) {
            $compressed = snappy_compress($payload);

            if (is_string($compressed)) {
                return $compressed;
            }
        }

        return $this->snappyRawBlock($payload);
    }

    private function encodeTimeSeries(TimeSeries $series): string
    {
        $out = "";

        foreach ($series->labels as $label) {
            $out .= $this->lengthDelimited(1, $this->encodeLabel($label));
        }

        foreach ($series->samples as $sample) {
            $out .= $this->lengthDelimited(2, $this->encodeSample($sample));
        }

        return $out;
    }

    private function encodeLabel(Label $label): string
    {
        return $this->lengthDelimited(1, $label->name) . $this->lengthDelimited(2, $label->value);
    }

    private function encodeSample(Sample $sample): string
    {
        $value = chr((1 << 3) | 1) . pack("e", $sample->value);

        $timestamp = chr((2 << 3) | 0) . $this->varint($sample->timestampMs);

        return $value . $timestamp;
    }

    private function lengthDelimited(int $fieldNumber, string $payload): string
    {
        return $this->varint(($fieldNumber << 3) | 2) . $this->varint(strlen($payload)) . $payload;
    }

    private function varint(int $value): string
    {
        $out = "";
        $remaining = $value;

        do {
            $byte = $remaining&0x7F;
            $remaining >>= 7;

            if ($remaining !== 0) {
                $byte |= 0x80;
            }

            $out .= chr($byte);
        } while ($remaining !== 0);

        return $out;
    }

    private function snappyRawBlock(string $payload): string
    {
        $length = strlen($payload);
        $out = $this->varint($length);
        $offset = 0;

        while ($offset < $length) {
            $chunk = min($length - $offset, 65_536);
            $out .= $this->snappyLiteralTag($chunk);
            $out .= substr($payload, $offset, $chunk);
            $offset += $chunk;
        }

        return $out;
    }

    private function snappyLiteralTag(int $runLength): string
    {
        $n = $runLength - 1;

        if ($n < 60) {
            return chr($n << 2);
        }

        $lengthBytes = "";
        $value = $n;

        do {
            $lengthBytes .= chr($value&0xFF);
            $value >>= 8;
        } while ($value !== 0);

        $tag = (59 + strlen($lengthBytes)) << 2;

        return chr($tag) . $lengthBytes;
    }
}
