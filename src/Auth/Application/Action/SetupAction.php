<?php

declare(strict_types=1);

namespace App\Auth\Application\Action;

use App\Auth\Application\Command\CreateAdmin\AdminAlreadyExists;
use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use App\Auth\Application\WeakPassword;
use App\Auth\Domain\UserRepository;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

use function is_string;

final class SetupAction extends AbstractController
{
    private const CSRF_TOKEN_ID = "setup";

    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly UserRepository $users,
    ) {}

    #[Route("/setup", name: "auth.setup", methods: ["GET", "POST"])]
    public function __invoke(Request $request): Response
    {
        if ($this->users->count() > 0) {
            throw new NotFoundHttpException();
        }

        if ($request->isMethod(Request::METHOD_GET)) {
            return $this->render("security/setup.html.twig");
        }

        $token = $request->request->get("_csrf_token");

        if (!is_string($token) || !$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $token)) {
            return $this->renderWithError("Your session expired. Please try again.");
        }

        $email = (string)$request->request->get("email", "");
        $password = (string)$request->request->get("password", "");
        $passwordConfirm = (string)$request->request->get("passwordConfirm", "");

        if ($password !== $passwordConfirm) {
            return $this->renderWithError("The passwords do not match.", $email);
        }

        try {
            $this->commandBus->dispatch(new CreateAdminCommand($email, $password));
        } catch (HandlerFailedException $exception) {
            return $this->renderWithError($this->friendlyMessage($exception), $email);
        }

        return new RedirectResponse("/login");
    }

    private function friendlyMessage(HandlerFailedException $exception): string
    {
        $cause = $exception->getPrevious() ?? $exception;

        return match (true) {
            $cause instanceof WeakPassword => "The password must be at least 12 characters long.",
            $cause instanceof AdminAlreadyExists => "An administrator account already exists.",
            $cause instanceof InvalidArgumentException => "Please enter a valid email address.",
            default => "We could not create the account. Please check your details and try again.",
        };
    }

    private function renderWithError(string $error, string $email = ""): Response
    {
        return $this->render("security/setup.html.twig", [
            "error" => $error,
            "email" => $email,
        ], new Response("", Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
