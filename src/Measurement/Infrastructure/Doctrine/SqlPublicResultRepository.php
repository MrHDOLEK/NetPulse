<?php

declare(strict_types=1);

namespace App\Measurement\Infrastructure\Doctrine;

use App\Measurement\Application\PublicResult\PublicResult;
use App\Measurement\Application\PublicResult\PublicResultRepository;
use App\Measurement\Application\PublicResult\ResultNotFound;
use App\Measurement\Domain\Entity\Measurement;
use App\Measurement\Domain\Enum\MeasurementStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(id: PublicResultRepository::class, public: true)]
final readonly class SqlPublicResultRepository implements PublicResultRepository
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {}

    public function get(string $shareToken): PublicResult
    {
        /**
         * @var array{
         *     downloadBits: int|null,
         *     uploadBits: int|null,
         *     pingSeconds: float|null,
         *     jitterSeconds: float|null,
         *     lossRatio: float|null,
         *     serverName: string,
         *     serverLocation: string,
         *     isp: string,
         *     completedAt: DateTimeImmutable,
         *     status: MeasurementStatus,
         *     healthy: bool|null
         * } $row
         */
        $row = $this->fetchRow($shareToken);

        return new PublicResult(
            downloadBits: $row['downloadBits'],
            uploadBits: $row['uploadBits'],
            pingSeconds: $row['pingSeconds'],
            jitterSeconds: $row['jitterSeconds'],
            lossRatio: $row['lossRatio'],
            serverName: $row['serverName'],
            serverLocation: $row['serverLocation'],
            isp: $row['isp'],
            completedAtUnix: $row['completedAt']->getTimestamp(),
            status: $row['status'],
            healthy: $row['healthy'],
        );
    }

    /**
     * @throws ResultNotFound
     *
     * @return array<string,mixed>
     */
    private function fetchRow(string $shareToken): array
    {
        try {
            /** @var array<string,mixed> $row */
            $row = $this->entityManager
                ->createQueryBuilder()
                ->select(
                    'measurement.downloadBits AS downloadBits',
                    'measurement.uploadBits AS uploadBits',
                    '(measurement.ping / 1000.0) AS pingSeconds',
                    '(measurement.jitter / 1000.0) AS jitterSeconds',
                    'measurement.packetLossRatio AS lossRatio',
                    'measurement.serverName AS serverName',
                    'measurement.serverLocation AS serverLocation',
                    'measurement.isp AS isp',
                    'measurement.completedAt AS completedAt',
                    'measurement.status AS status',
                    'measurement.healthy AS healthy',
                )
                ->from(Measurement::class, 'measurement')
                ->where('measurement.shareToken = :token')
                ->setParameter('token', $shareToken)
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleResult();
        } catch (NoResultException) {
            throw new ResultNotFound('No shared result for the given token.');
        }

        return $row;
    }
}
