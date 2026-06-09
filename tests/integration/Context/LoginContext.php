<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context;

use App\Auth\Application\Command\CreateAdmin\CreateAdminCommand;
use Behat\Behat\Context\Context;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

use function sprintf;
use function str_contains;

final class LoginContext implements Context
{
    private const CSRF_TOKEN_ID = "authenticate";
    private const CSRF_RAW_TOKEN = "behat-login-token";

    private Response $response;
    private Session $session;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly MessageBusInterface $commandBus,
    ) {
        $this->session = new Session(new MockArraySessionStorage());

        $this->session->start();
    }

    /**
     * @Given an administrator account exists with email :email and password :password
     */
    public function anAdministratorAccountExistsWith(string $email, string $password): void
    {
        $this->commandBus->dispatch(new CreateAdminCommand($email, $password));
    }

    /**
     * @When I send a GET request to :path
     * @When I send an authenticated GET request to :path
     */
    public function iSendAGetRequestTo(string $path): void
    {
        $request = Request::create($path, "GET");
        $this->attachSession($request);

        $this->response = $this->kernel->handle($request);
    }

    /**
     * @When I log in with email :email and password :password
     */
    public function iLogInWith(string $email, string $password): void
    {
        $this->session->set(
            SessionTokenStorage::SESSION_NAMESPACE . "/" . self::CSRF_TOKEN_ID,
            self::CSRF_RAW_TOKEN,
        );

        $request = Request::create("/login", "POST", [
            "_username" => $email,
            "_password" => $password,
            "_csrf_token" => self::CSRF_RAW_TOKEN,
        ]);
        $this->attachSession($request);

        $this->response = $this->kernel->handle($request);
    }

    /**
     * @When I follow the redirect
     */
    public function iFollowTheRedirect(): void
    {
        $location = (string)$this->response->headers->get("Location");

        if ($location === "") {
            throw new RuntimeException("The previous response had no Location header to follow.");
        }

        $request = Request::create($location, "GET");
        $this->attachSession($request);

        $this->response = $this->kernel->handle($request);
    }

    /**
     * @Then the response code is :code
     */
    public function theResponseCodeIs(int $code): void
    {
        if ($this->response->getStatusCode() !== $code) {
            throw new RuntimeException(
                sprintf("Response code is %d, %d expected.", $this->response->getStatusCode(), $code),
            );
        }
    }

    /**
     * @Then the response content type contains :fragment
     */
    public function theResponseContentTypeContains(string $fragment): void
    {
        $contentType = (string)$this->response->headers->get("Content-Type");

        if (!str_contains($contentType, $fragment)) {
            throw new RuntimeException(
                sprintf("Response content type \"%s\" does not contain \"%s\".", $contentType, $fragment),
            );
        }
    }

    /**
     * @Then the response redirects to a location containing :fragment
     */
    public function theResponseRedirectsToALocationContaining(string $fragment): void
    {
        $location = (string)$this->response->headers->get("Location");

        if (!str_contains($location, $fragment)) {
            throw new RuntimeException(
                sprintf("Redirect Location \"%s\" does not contain \"%s\".", $location, $fragment),
            );
        }
    }

    /**
     * @Then the response body contains :fragment
     */
    public function theResponseBodyContains(string $fragment): void
    {
        $body = (string)$this->response->getContent();

        if (!str_contains($body, $fragment)) {
            throw new RuntimeException(
                sprintf("Response body does not contain \"%s\".", $fragment),
            );
        }
    }

    private function attachSession(Request $request): void
    {
        $request->setSession($this->session);
        $request->cookies->set($this->session->getName(), $this->session->getId());
    }
}
