<?php

declare(strict_types=1);

namespace App\Tests\Integration\Context;

use Behat\Behat\Context\Context;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;

use function sprintf;
use function str_contains;

final class SecurityHeadersContext implements Context
{
    private Response $response;

    public function __construct(
        private readonly KernelInterface $kernel,
    ) {}

    /**
     * @When I request :path
     */
    public function iRequest(string $path): void
    {
        $this->response = $this->kernel->handle(Request::create($path, "GET"));
    }

    /**
     * @Then the response has header :name with value :value
     */
    public function theResponseHasHeaderWithValue(string $name, string $value): void
    {
        $actual = $this->response->headers->get($name);

        if ($actual !== $value) {
            throw new RuntimeException(
                sprintf("Header \"%s\" is \"%s\", expected \"%s\".", $name, (string)$actual, $value),
            );
        }
    }

    /**
     * @Then the response header :name contains :fragment
     */
    public function theResponseHeaderContains(string $name, string $fragment): void
    {
        $actual = (string)$this->response->headers->get($name);

        if (!str_contains($actual, $fragment)) {
            throw new RuntimeException(
                sprintf("Header \"%s\" is \"%s\", which does not contain \"%s\".", $name, $actual, $fragment),
            );
        }
    }

    /**
     * @Then the response header :name matches :pattern
     */
    public function theResponseHeaderMatches(string $name, string $pattern): void
    {
        $actual = (string)$this->response->headers->get($name);

        if (preg_match($pattern, $actual) !== 1) {
            throw new RuntimeException(
                sprintf("Header \"%s\" is \"%s\", which does not match \"%s\".", $name, $actual, $pattern),
            );
        }
    }

    /**
     * @Then the response does not have header :name
     */
    public function theResponseDoesNotHaveHeader(string $name): void
    {
        if ($this->response->headers->has($name)) {
            throw new RuntimeException(
                sprintf("Header \"%s\" is present (\"%s\") but was expected to be absent.", $name, (string)$this->response->headers->get($name)),
            );
        }
    }
}
