<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\PropertyInfo\Extractor\ConstructorExtractor;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;

final class OoklaSerializerFactory
{
    public static function create(): SerializerInterface
    {
        $reflectionExtractor = new ReflectionExtractor();
        $phpDocExtractor = new PhpDocExtractor();

        $propertyInfo = new PropertyInfoExtractor(
            typeExtractors: [
                new ConstructorExtractor([$reflectionExtractor]),
                $phpDocExtractor,
                $reflectionExtractor,
            ],
        );

        $normalizer = new ObjectNormalizer(
            propertyTypeExtractor: $propertyInfo,
        );

        return new Serializer(
            [$normalizer, new ArrayDenormalizer()],
            [new JsonEncoder()],
        );
    }
}
