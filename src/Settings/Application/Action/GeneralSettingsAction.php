<?php

declare(strict_types=1);

namespace App\Settings\Application\Action;

use App\Settings\Application\SaveSettings\SaveSettingsCommand;
use App\Settings\Application\SettingsException;
use App\Settings\Application\SettingsReader;
use App\Settings\Domain\SettingKey;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;
use function is_string;
use function json_decode;
use function trim;

#[IsGranted('ROLE_ADMIN')]
final class GeneralSettingsAction extends AbstractController
{
    private const string CSRF_TOKEN_ID = 'settings-general';

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly SettingsReader $settings,
    ) {}

    #[Route('/settings/general', name: 'settings_general', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('settings/general/index.html.twig', [
            'siteName' => $this->settings->getString(SettingKey::SiteName),
            'timezone' => $this->settings->getString(SettingKey::Timezone),
        ]);
    }

    #[Route('/settings/general', name: 'settings_general_save', methods: ['POST'])]
    public function save(Request $request): Response
    {
        $token = $request->headers->get('X-CSRF-Token');

        if (!is_string($token) || !$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            return new JsonResponse(['error' => 'Invalid CSRF token'], Response::HTTP_FORBIDDEN);
        }

        $body = json_decode($request->getContent(), true);
        $body = is_array($body) ? $body : [];

        $command = new SaveSettingsCommand([
            SettingKey::SiteName->value => $this->str($body, 'siteName'),
            SettingKey::Timezone->value => $this->str($body, 'timezone'),
        ]);

        try {
            $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            $cause = $exception->getPrevious() ?? $exception;

            if ($cause instanceof SettingsException) {
                return new JsonResponse(['error' => $cause->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            throw $exception;
        }

        return new JsonResponse([
            'saved' => true,
            'siteName' => $this->settings->getString(SettingKey::SiteName),
            'timezone' => $this->settings->getString(SettingKey::Timezone),
        ]);
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function str(array $body, string $key): string
    {
        $value = $body[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }
}
