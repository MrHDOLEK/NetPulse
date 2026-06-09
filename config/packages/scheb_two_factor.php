<?php

declare(strict_types=1);

use App\Auth\Infrastructure\Security\RecoveryCodeManager;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;


return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension("scheb_two_factor", [
        "security_tokens" => [
            UsernamePasswordToken::class,
            PostAuthenticationToken::class,
        ],
        "totp" => [
            "enabled" => true,
            "issuer" => "NetPulse",
            "server_name" => "NetPulse",
            "template" => "security/2fa_form.html.twig",
            
            
            
            "leeway" => 10,
        ],
        "backup_codes" => [
            "enabled" => true,
            "manager" => RecoveryCodeManager::class,
        ],
    ]);
};
