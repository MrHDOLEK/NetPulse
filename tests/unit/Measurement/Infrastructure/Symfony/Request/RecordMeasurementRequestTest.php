<?php

declare(strict_types=1);

namespace App\Tests\Unit\Measurement\Infrastructure\Symfony\Request;

use App\Measurement\Application\Ookla\OoklaResult;
use App\Measurement\Infrastructure\Symfony\Request\RecordMeasurementRequest;
use App\Shared\Infrastructure\Symfony\Request\Validator\RequestValidator;
use App\Shared\Infrastructure\Symfony\Request\Validator\ValidationError;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

final class RecordMeasurementRequestTest extends TestCase
{
    public function testValidWhenConnectionIdIsAUuidAndTypeIsPresent(): void
    {
        $request = new RecordMeasurementRequest();
        $request->connectionId = '33333333-3333-4333-8333-333333333333';
        $request->scheduled = true;
        $request->setRawBody(new OoklaResult('result'), ['type' => 'result']);

        $this->validator()->validate($request);

        $this->addToAssertionCount(1);
    }

    public function testValidWhenTypeIsAFailedNonResultType(): void
    {
        $request = new RecordMeasurementRequest();
        $request->connectionId = '33333333-3333-4333-8333-333333333333';
        $request->setRawBody(new OoklaResult('error'), ['type' => 'error']);

        $this->validator()->validate($request);

        $this->addToAssertionCount(1);
    }

    public function testInvalidWhenTypeMissing(): void
    {
        $request = new RecordMeasurementRequest();
        $request->connectionId = '33333333-3333-4333-8333-333333333333';

        try {
            $this->validator()->validate($request);
            $this->fail('Expected ValidationError.');
        } catch (ValidationError $error) {
            $this->assertArrayHasKey('ooklaType', $error->getErrors());
            $this->assertContains('VALIDATION.TYPE_REQUIRED', $error->getErrors()['ooklaType']);
        }
    }

    public function testInvalidWhenConnectionIdMissing(): void
    {
        $request = new RecordMeasurementRequest();
        $request->connectionId = '';

        try {
            $this->validator()->validate($request);
            $this->fail('Expected ValidationError.');
        } catch (ValidationError $error) {
            $this->assertArrayHasKey('connectionId', $error->getErrors());
        }
    }

    public function testInvalidWhenConnectionIdNotUuid(): void
    {
        $request = new RecordMeasurementRequest();
        $request->connectionId = 'not-a-uuid';

        $this->expectException(ValidationError::class);

        $this->validator()->validate($request);
    }

    public function testDeclaresOoklaResultAsRawBodyType(): void
    {
        $this->assertSame(OoklaResult::class, RecordMeasurementRequest::rawBodyType());
    }

    public function testSetRawBodyStoresTypedResultAndVerbatimPayload(): void
    {
        $request = new RecordMeasurementRequest();
        $result = new OoklaResult('result');
        $payload = ['type' => 'result', 'extra' => 'kept'];

        $request->setRawBody($result, $payload);

        $this->assertSame($result, $request->ookla);
        $this->assertSame($payload, $request->raw);
    }

    private function validator(): RequestValidator
    {
        return new RequestValidator(Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator());
    }
}
