<?php

declare(strict_types=1);

namespace App\Auth\Application\Action;

use App\Auth\Infrastructure\Oidc\OidcConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginAction extends AbstractController
{
    public function __construct(
        private readonly OidcConfig $oidcConfig,
    ) {}

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function __invoke(AuthenticationUtils $authenticationUtils): Response
    {
        $providers = $this->oidcConfig->isEnabled()
            ? [['name' => $this->oidcConfig->displayName(), 'url' => '/login/oidc/start']]
            : [];

        return $this->render('security/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
            'providers' => $providers,
        ]);
    }
}
