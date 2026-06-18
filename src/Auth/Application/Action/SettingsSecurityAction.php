<?php

declare(strict_types=1);

namespace App\Auth\Application\Action;

use App\Auth\Application\Command\DisableTotp\DisableTotpCommand;
use App\Auth\Application\Command\EnableTotp\EnableTotpCommand;
use App\Auth\Application\Command\RegenerateRecoveryCodes\RegenerateRecoveryCodesCommand;
use App\Auth\Application\RecoveryCode\RecoveryCodeGenerator;
use App\Auth\Domain\Entity\User\User;
use App\Auth\Infrastructure\Symfony\Security\SecurityUser;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use LogicException;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;
use function preg_replace;
use function strtoupper;
use function trim;

#[IsGranted('ROLE_ADMIN')]
final class SettingsSecurityAction extends AbstractController
{
    private const string SESSION_SETUP_SECRET = 'twofa_setup_secret';
    private const string CSRF_BEGIN = 'twofa_begin';
    private const string CSRF_CONFIRM = 'twofa_confirm';
    private const string CSRF_DISABLE = 'twofa_disable';
    private const string CSRF_REGENERATE = 'twofa_regenerate';
    private const string ISSUER = 'NetPulse';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly RecoveryCodeGenerator $recoveryCodes,
    ) {}

    #[Route('/settings/security', name: 'settings_security', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('settings/security.html.twig', [
            'hasTotp' => $this->currentUser()->hasTotp(),
        ]);
    }

    #[Route('/settings/security/2fa/begin', name: 'settings_security_2fa_begin', methods: ['POST'])]
    public function begin(Request $request): Response
    {
        if (!$this->csrfValid($request, self::CSRF_BEGIN)) {
            return $this->expired();
        }

        $user = $this->currentUser();

        $secret = TOTP::generate(secretSize: 20)->getSecret();
        $request->getSession()->set(self::SESSION_SETUP_SECRET, $secret);

        return $this->renderEnrol($user, $secret);
    }

    #[Route('/settings/security/2fa/confirm', name: 'settings_security_2fa_confirm', methods: ['POST'])]
    public function confirm(Request $request): Response
    {
        if (!$this->csrfValid($request, self::CSRF_CONFIRM)) {
            return $this->expired();
        }

        $user = $this->currentUser();
        $session = $request->getSession();
        $secret = $session->get(self::SESSION_SETUP_SECRET);

        if (!is_string($secret) || $secret === '') {
            return new RedirectResponse($this->generateUrl('settings_security'));
        }

        $code = $this->digits((string) $request->request->get('code', ''));

        if ($code === '' || !TOTP::createFromSecret($secret)->verify($code, null, 10)) {
            return $this->renderEnrol(
                $user,
                $secret,
                'That code did not match. Check your authenticator app and try again.',
            );
        }

        $generated = $this->recoveryCodes->generate();
        $this->commandBus->dispatch(new EnableTotpCommand($user->id()->toString(), $secret, $generated->hashed));

        $session->remove(self::SESSION_SETUP_SECRET);

        return $this->render('settings/2fa_recovery_codes.html.twig', [
            'codes' => $generated->plain,
            'regenerated' => false,
        ]);
    }

    #[Route('/settings/security/2fa/disable', name: 'settings_security_2fa_disable', methods: ['POST'])]
    public function disable(Request $request): Response
    {
        if (!$this->csrfValid($request, self::CSRF_DISABLE)) {
            return $this->expired();
        }

        $user = $this->currentUser();

        $confirm = trim((string) $request->request->get('confirm', ''));

        if (strtoupper($confirm) !== 'DISABLE') {
            $this->addFlash('error', 'Type DISABLE to confirm turning off two-factor authentication.');

            return new RedirectResponse($this->generateUrl('settings_security'));
        }

        if ($user->hasTotp()) {
            $this->commandBus->dispatch(new DisableTotpCommand($user->id()->toString()));
        }

        $request->getSession()->remove(self::SESSION_SETUP_SECRET);

        $this->addFlash('success', 'Two-factor authentication has been disabled.');

        return new RedirectResponse($this->generateUrl('settings_security'));
    }

    #[Route(
        '/settings/security/2fa/recovery/regenerate',
        name: 'settings_security_2fa_recovery_regenerate',
        methods: ['POST'],
    )]
    public function regenerateRecoveryCodes(Request $request): Response
    {
        if (!$this->csrfValid($request, self::CSRF_REGENERATE)) {
            return $this->expired();
        }

        $user = $this->currentUser();

        if (!$user->hasTotp()) {
            return new RedirectResponse($this->generateUrl('settings_security'));
        }

        $generated = $this->recoveryCodes->generate();
        $this->commandBus->dispatch(new RegenerateRecoveryCodesCommand($user->id()->toString(), $generated->hashed));

        return $this->render('settings/2fa_recovery_codes.html.twig', [
            'codes' => $generated->plain,
            'regenerated' => true,
        ]);
    }

    private function renderEnrol(User $user, string $secret, ?string $error = null): Response
    {
        if ($secret === '') {
            throw new LogicException('Enrolment secret must not be empty.');
        }

        $email = $user->email()->value();

        $totp = TOTP::createFromSecret($secret);
        $totp->setLabel($email);
        $totp->setIssuer(self::ISSUER);

        $qrDataUri = new Builder(writer: new PngWriter(), data: $totp->getProvisioningUri(), size: 220, margin: 8)
            ->build()
            ->getDataUri();

        $status = $error === null ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY;

        return $this->render(
            'settings/2fa_enroll.html.twig',
            [
                'manualKey' => $secret,
                'qrDataUri' => $qrDataUri,
                'error' => $error,
            ],
            new Response('', $status),
        );
    }

    private function digits(string $value): string
    {
        return (string) preg_replace("/\D+/", '', $value);
    }

    private function currentUser(): User
    {
        $securityUser = $this->getUser();

        if (!$securityUser instanceof SecurityUser) {
            throw new LogicException('Expected an authenticated SecurityUser.');
        }

        return $securityUser->getUser();
    }

    private function csrfValid(Request $request, string $id): bool
    {
        $token = $request->request->get('_csrf_token');

        return is_string($token) && $this->isCsrfTokenValid($id, $token);
    }

    private function expired(): Response
    {
        $this->addFlash('error', 'Your session expired. Please try again.');

        return new RedirectResponse($this->generateUrl('settings_security'));
    }
}
