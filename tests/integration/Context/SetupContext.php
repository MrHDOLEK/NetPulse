<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context;

use App\Auth\Domain\Entity\User\User;
use App\Auth\Domain\Entity\User\UserId;
use App\Auth\Domain\Entity\User\UserRoleCollection;
use App\Auth\Domain\UserRepository;
use App\Auth\Domain\ValueObject\Email;
use App\Auth\Domain\ValueObject\HashedPassword;
use Behat\Behat\Context\Context;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

use function sprintf;
use function str_contains;

final class SetupContext implements Context
{
    private const CSRF_TOKEN_ID = "setup";
    private const CSRF_RAW_TOKEN = "behat-setup-token";

    private Response $response;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly UserRepository $users,
    ) {}

    /**
     * @Given no users exist
     */
    public function noUsersExist(): void
    {
        if ($this->users->count() !== 0) {
            throw new RuntimeException("Expected an empty user table at the start of the scenario.");
        }
    }

    /**
     * @Given an admin user already exists
     */
    public function anAdminUserAlreadyExists(): void
    {
        $this->users->save(User::register(
            new UserId("11111111-1111-4111-8111-111111111111"),
            new Email("existing-admin@example.com"),
            HashedPassword::fromHash("already-hashed"),
            UserRoleCollection::fromStrings(["ROLE_ADMIN"]),
            new DateTimeImmutable("2026-06-01T00:00:00+00:00"),
        ));
    }

    /**
     * @When I send a GET request to :path
     */
    public function iSendAGetRequestTo(string $path): void
    {
        $this->response = $this->kernel->handle(Request::create($path, "GET"));
    }

    /**
     * @When I submit the setup form with email :email and password :password
     */
    public function iSubmitTheSetupFormWith(string $email, string $password): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set(
            SessionTokenStorage::SESSION_NAMESPACE . "/" . self::CSRF_TOKEN_ID,
            self::CSRF_RAW_TOKEN,
        );

        $request = Request::create("/setup", "POST", [
            "email" => $email,
            "password" => $password,
            "passwordConfirm" => $password,
            "_csrf_token" => self::CSRF_RAW_TOKEN,
        ]);
        $request->setSession($session);

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

    /**
     * @Then exactly :count user exists
     * @Then exactly :count users exist
     */
    public function exactlyUsersExist(int $count): void
    {
        if ($this->users->count() !== $count) {
            throw new RuntimeException(
                sprintf("Expected exactly %d user(s), found %d.", $count, $this->users->count()),
            );
        }
    }
}
