<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Symfony\Request\Resolver;

use App\Shared\Infrastructure\Symfony\Request\Validator\ValidationError;
use App\Shared\Infrastructure\Utils\Request\DeserializesRawBody;
use App\Shared\Infrastructure\Utils\Request\RequestInterface;
use Generator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\PropertyAccess\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

use function in_array;
use function is_array;
use function is_object;
use function json_decode;

final class JsonBodyResolver implements ValueResolverInterface
{
    private const string FORMAT = "json";

    public function __construct(
        private SerializerInterface $serializer,
    ) {}

    /**
     * @throws ValidationError
     *
     * @return Generator
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if (!$this->supports($argument)) {
            return;
        }

        $content = empty($request->getContent()) ? "{}" : $request->getContent();

        try {
            $resolved = $this->serializer->deserialize(
                $content,
                $argument->getType() ?? "",
                self::FORMAT,
                [
                    AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
                ],
            );

            if ($resolved instanceof DeserializesRawBody) {
                $this->attachRawBody($resolved, $content);
            }

            yield $resolved;
        } catch (UnexpectedValueException|InvalidArgumentException|RuntimeException) {
            throw new ValidationError([
                ValidationError::GENERAL => "VALIDATION.INVALID_PAYLOAD",
            ]);
        }
    }

    private function attachRawBody(DeserializesRawBody $request, string $content): void
    {
        $body = $this->serializer->deserialize(
            $content,
            $request::rawBodyType(),
            self::FORMAT,
            [
                AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT => true,
            ],
        );

        if (!is_object($body)) {
            return;
        }

        $decoded = json_decode($content, true);

        /** @var array<string,mixed> $payload */
        $payload = is_array($decoded) ? $decoded : [];

        $request->setRawBody($body, $payload);
    }

    private function supports(ArgumentMetadata $argument): bool
    {
        $type = (string)$argument->getType();

        if (!class_exists($type)) {
            return false;
        }

        return in_array(RequestInterface::class, class_implements($type), true);
    }
}
