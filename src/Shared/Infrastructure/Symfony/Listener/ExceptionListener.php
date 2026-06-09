<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Listener;

use App\Connection\Domain\Exception\ConnectionNotOwnedByProbe;
use App\Probe\Domain\Exception\InvalidProbeToken;
use App\Probe\Domain\Exception\ProbeDisabled;
use App\Shared\Domain\NotFoundException;
use App\Shared\Infrastructure\Symfony\Request\Validator\ValidationError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ExceptionListener
{
    public function __construct(
        private readonly string $environment,
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $path = $event->getRequest()->getPathInfo();
        $isApi = str_starts_with($path, "/api/") || $path === "/metrics";

        if (!$isApi) {
            return;
        }

        $exception = $event->getThrowable();

        $content = [];

        if ($exception instanceof ValidationError) {
            $code = Response::HTTP_BAD_REQUEST;
            $content["errors"] = $exception->getErrors();
        } elseif ($exception instanceof NotFoundException) {
            $code = Response::HTTP_NOT_FOUND;
        } elseif ($exception instanceof InvalidProbeToken) {
            $code = Response::HTTP_UNAUTHORIZED;
        } elseif ($exception instanceof ProbeDisabled) {
            $code = Response::HTTP_FORBIDDEN;
        } elseif ($exception instanceof ConnectionNotOwnedByProbe) {
            $code = Response::HTTP_FORBIDDEN;
        } elseif ($exception instanceof NotFoundHttpException) {
            $code = $exception->getStatusCode();
        } elseif ($exception instanceof MethodNotAllowedHttpException) {
            $code = $exception->getStatusCode();
        } else {
            $code = Response::HTTP_INTERNAL_SERVER_ERROR;

            if ($this->environment !== "prod") {
                $content["errors"] = [
                    "server" => $exception::class . " - " . $event->getThrowable()->getMessage(),
                ];
            }
        }

        $event->setResponse(new JsonResponse($content ?: null, $code));
    }
}
