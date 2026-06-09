<?php

declare(strict_types=1);

use App\Auth\Infrastructure\Symfony\Security\OidcAuthenticator;
use App\Auth\Infrastructure\Symfony\Security\UserProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension("security", [
        
        
        "session_fixation_strategy" => "migrate",
        "password_hashers" => [
            PasswordAuthenticatedUserInterface::class => [
                "algorithm" => "auto",
            ],
        ],
        "providers" => [
            "app_users" => [
                "id" => UserProvider::class,
            ],
        ],
        "firewalls" => [
            
            
            
            "dev" => [
                "pattern" => "^/(_(profiler|wdt)|css|images|js)/",
                "security" => false,
            ],
            
            
            
            "api" => [
                "pattern" => "^/(api|metrics)",
                "security" => false,
            ],
            
            
            
            
            "public_result" => [
                "pattern" => "^/r/",
                "security" => false,
            ],
            
            "main" => [
                "pattern" => "^/",
                "lazy" => true,
                "provider" => "app_users",
                
                
                
                "custom_authenticators" => [
                    OidcAuthenticator::class,
                ],
                "form_login" => [
                    "login_path" => "/login",
                    "check_path" => "/login",
                    "enable_csrf" => true,
                    "default_target_path" => "/",
                    
                    
                    
                    "always_use_default_target_path" => true,
                ],
                "logout" => [
                    "path" => "/logout",
                    "target" => "/login",
                ],
                "login_throttling" => [
                    "max_attempts" => 5,
                ],
                
                
                
                "two_factor" => [
                    "auth_form_path" => "2fa_login",
                    "check_path" => "2fa_login_check",
                    "default_target_path" => "/",
                    "always_use_default_target_path" => true,
                    "prepare_on_login" => true,
                    "enable_csrf" => true,
                ],
            ],
        ],
        "access_control" => [
            
            
            ["path" => "^/login/oidc", "roles" => "PUBLIC_ACCESS"],
            ["path" => "^/(login|setup|logout)", "roles" => "PUBLIC_ACCESS"],
            ["path" => "^/assets", "roles" => "PUBLIC_ACCESS"],
            ["path" => "^/r/", "roles" => "PUBLIC_ACCESS"],
            
            
            
            ["path" => "^/2fa", "roles" => "IS_AUTHENTICATED_2FA_IN_PROGRESS"],
            ["path" => "^/", "roles" => "ROLE_ADMIN"],
        ],
    ]);

    if ($containerConfigurator->env() === "test") {
        $containerConfigurator->extension("security", [
            "password_hashers" => [
                PasswordAuthenticatedUserInterface::class => [
                    "algorithm" => "auto",
                    "cost" => 4,
                    "time_cost" => 3,
                    "memory_cost" => 10,
                ],
            ],
        ]);
    }
};
