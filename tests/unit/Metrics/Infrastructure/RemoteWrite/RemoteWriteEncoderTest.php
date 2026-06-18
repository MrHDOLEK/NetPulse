<?php

declare(strict_types=1);

namespace App\Tests\Unit\Metrics\Infrastructure\RemoteWrite;

use App\Metrics\Domain\RemoteWrite\Collection\LabelCollection;
use App\Metrics\Domain\RemoteWrite\Collection\SampleCollection;
use App\Metrics\Domain\RemoteWrite\Collection\TimeSeriesCollection;
use App\Metrics\Domain\RemoteWrite\ValueObject\Label;
use App\Metrics\Domain\RemoteWrite\ValueObject\Sample;
use App\Metrics\Domain\RemoteWrite\ValueObject\TimeSeries;
use App\Metrics\Infrastructure\RemoteWrite\RemoteWriteEncoder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RemoteWriteEncoderTest extends TestCase
{
    public function testEncodesWriteRequestToKnownProtobufBytes(): void
    {
        $encoder = new RemoteWriteEncoder();

        $series = new TimeSeries(
            LabelCollection::of(new Label('__name__', 'up'), new Label('probe', 'p1')),
            SampleCollection::of(new Sample(1.0, 1)),
        );

        $expected =
            "\x0a\x2a"
            . "\x0a\x0e\x0a\x08__name__\x12\x02up"
            . "\x0a\x0b\x0a\x05probe\x12\x02p1"
            . "\x12\x0b\x09\x00\x00\x00\x00\x00\x00\xf0\x3f\x10\x01";

        self::assertSame(bin2hex($expected), bin2hex($encoder->encodeWriteRequest(TimeSeriesCollection::of($series))));
    }

    public function testSnappyBlockRoundTripsThroughProvidedDecoder(): void
    {
        $encoder = new RemoteWriteEncoder();
        $payload = 'hello-remote-write-payload';

        $compressed = $encoder->snappy($payload);

        self::assertSame($payload, $this->decodeSnappyRawBlock($compressed));
    }

    public function testSnappyEmitsUncompressedLengthPreamble(): void
    {
        $encoder = new RemoteWriteEncoder();

        $compressed = $encoder->snappy(str_repeat('a', 5));

        self::assertSame(5, ord($compressed[0]));
    }

    private function decodeSnappyRawBlock(string $data): string
    {
        $pos = 0;
        $shift = 0;

        do {
            $byte = ord($data[$pos]);
            $pos++;
            $shift += 7;
        } while (($byte & 0x80) !== 0 && $shift < 64);

        $out = '';
        $len = strlen($data);

        while ($pos < $len) {
            $tag = ord($data[$pos]);
            $pos++;
            $type = $tag & 0x03;

            if ($type !== 0) {
                throw new RuntimeException("Unexpected snappy element type {$type}.");
            }

            $litLen = $tag >> 2;

            if ($litLen < 60) {
                $runLen = $litLen + 1;
            } else {
                $extra = $litLen - 59;
                $runLen = 0;

                for ($i = 0; $i < $extra; $i++) {
                    $runLen |= ord($data[$pos]) << (8 * $i);
                    $pos++;
                }

                $runLen++;
            }

            $out .= substr($data, $pos, $runLen);
            $pos += $runLen;
        }

        return $out;
    }
}
