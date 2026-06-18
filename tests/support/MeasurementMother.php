<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Connection\Domain\ValueObject\ConnectionId;
use App\Measurement\Application\Ookla\DefaultOoklaResultMapper;
use App\Measurement\Application\Ookla\OoklaResult;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\ValueObject\MeasurementId;
use App\Probe\Domain\ValueObject\ProbeId;
use DateTimeImmutable;

use function json_encode;

final class MeasurementMother
{
    /**
     * @param array<string,mixed> $oookla raw decoded Ookla CLI JSON
     */
    public static function fromOoklaArray(
        array $oookla,
        string $measurementId,
        string $probeId,
        string $connectionId,
        bool $scheduled,
        DateTimeImmutable $recordedAt,
    ): Measurement {
        $result = self::deserialize($oookla);

        return new DefaultOoklaResultMapper()->toMeasurement(
            new MeasurementId($measurementId),
            new ProbeId($probeId),
            new ConnectionId($connectionId),
            $result,
            $scheduled,
            $recordedAt,
            $oookla,
        );
    }

    /**
     * @param array<string,mixed> $ookla raw decoded Ookla CLI JSON
     */
    public static function deserialize(array $ookla): OoklaResult
    {
        $serializer = OoklaSerializerFactory::create();

        $result = $serializer->deserialize((string) json_encode($ookla), OoklaResult::class, 'json');

        return $result instanceof OoklaResult ? $result : new OoklaResult();
    }
}
