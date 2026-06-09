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
use Behat\Gherkin\Node\PyStringNode;
use DateTimeImmutable;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

final class ApiContext implements Context
{
    private Response $response;
    private ?string $authToken = null;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly UserRepository $users,
    ) {}

    /**
     * @Given an administrator account exists
     */
    public function anAdministratorAccountExists(): void
    {
        $this->users->save(User::register(
            new UserId("11111111-1111-4111-8111-111111111111"),
            new Email("admin@example.com"),
            HashedPassword::fromHash("already-hashed"),
            UserRoleCollection::fromStrings(["ROLE_ADMIN"]),
            new DateTimeImmutable("2026-06-01T00:00:00+00:00"),
        ));
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    /**
     * @When I send a :method request to :path
     */
    public function iSendMethodRequest(string $method, string $path): Response
    {
        return $this->response = $this->kernel->handle($this->request($path, $method));
    }

    /**
     * @When I send a :method request to :path with body:
     */
    public function iSendMethodRequestWithBody(string $method, string $path, PyStringNode $content): Response
    {
        return $this->response = $this->kernel->handle($this->request($path, $method, $content->getRaw()));
    }

    /**
     * @Then the response code is :code
     */
    public function theResponseCodeIs(int $code): void
    {
        $this->assertResponseCode($code);
    }

    /**
     * @Then the response content is:
     */
    public function theResponseContentIs(PyStringNode $content): void
    {
        $this->assertResponseContent($content->getRaw());
    }

    /**
     * @Then the response content type contains :fragment
     */
    public function theResponseContentTypeContains(string $fragment): void
    {
        $contentType = (string)$this->getResponse()->headers->get("Content-Type");

        if (!str_contains($contentType, $fragment)) {
            throw new RuntimeException(
                sprintf("Response content type \"%s\" does not contain \"%s\".", $contentType, $fragment),
            );
        }
    }

    /**
     * @Given I authenticate as probe with token :token
     */
    public function iAuthenticateAsProbeWithToken(string $token): void
    {
        $this->authToken = $token;
    }

    private function request(
        string $path,
        string $method,
        ?string $body = null,
    ): Request {
        $headers = [
            "CONTENT_TYPE" => "application/json",
        ];

        if ($this->authToken !== null) {
            $headers["HTTP_AUTHORIZATION"] = "Bearer " . $this->authToken;
        }

        return Request::create(
            $path,
            $method,
            [],
            [],
            [],
            $headers,
            $body,
        );
    }

    private function assertResponseCode(int $code): void
    {
        if ($this->getResponse()->getStatusCode() !== $code) {
            throw new RuntimeException(
                sprintf("Response code is %d, %d expected", $this->response->getStatusCode(), $code),
            );
        }
    }

    private function assertResponseContent(string $expectedContent): void
    {
        $expectedContent = json_decode($expectedContent, true);
        $content = json_decode($this->getResponse()->getContent(), true);

        if ($content !== $expectedContent) {
            throw new RuntimeException("Response content is not as expected.");
        }
    }
}
