<?php

declare(strict_types=1);

namespace App\Settings\Infrastructure\Twig;

use App\Settings\Application\SettingsReader;
use App\Settings\Domain\SettingKey;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class SettingsExtension extends AbstractExtension
{
    private const string DEFAULT_SITE_NAME = "NetPulse";

    public function __construct(
        private readonly SettingsReader $settings,
    ) {}

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction("site_name", $this->siteName(...)),
        ];
    }

    public function siteName(): string
    {
        $name = $this->settings->getString(SettingKey::SiteName);

        return $name === "" ? self::DEFAULT_SITE_NAME : $name;
    }
}
