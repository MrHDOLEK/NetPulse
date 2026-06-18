<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Doctrine;

use App\Settings\Domain\AppSetting;
use App\Settings\Domain\AppSettingRepository;
use App\Settings\Domain\SettingKey;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AppSettingRepository::class)]
final class DoctrineAppSettingRepository implements AppSettingRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {}

    public function find(SettingKey $key): ?AppSetting
    {
        $setting = $this->entityManager
            ->createQueryBuilder()
            ->select('setting')
            ->from(AppSetting::class, 'setting')
            ->where('setting.key = :key')
            ->setParameter('key', $key, 'setting_key')
            ->getQuery()
            ->getOneOrNullResult();

        if ($setting === null) {
            return null;
        }

        if (!$setting instanceof AppSetting) {
            throw new LogicException('Expected query to return an AppSetting instance.');
        }

        return $setting;
    }

    public function save(AppSetting $setting): void
    {
        $this->entityManager->persist($setting);
        $this->entityManager->flush();
    }

    public function all(): array
    {
        /** @var list<AppSetting> $settings */
        $settings = $this->entityManager
            ->createQueryBuilder()
            ->select('setting')
            ->from(AppSetting::class, 'setting')
            ->getQuery()
            ->getResult();

        $byKey = [];

        foreach ($settings as $setting) {
            $byKey[$setting->key()->value] = $setting;
        }

        return $byKey;
    }
}
