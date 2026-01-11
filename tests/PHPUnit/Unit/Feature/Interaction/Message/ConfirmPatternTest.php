<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction\Message;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec Confirm pattern (ConfirmRequest, ConfirmResponse)
 * @see specs/features/interaction.yaml - message-types-confirm
 */
class ConfirmPatternTest extends TestCase
{
    public function testConfirmRequestHasDefaultValue(): void
    {
        $request = new ConfirmRequest(
            question: 'Deploy?',
            defaultValue: true
        );

        $this->assertTrue($request->defaultValue);
    }

    public function testConfirmRequestDefaultValueDefaultsToFalse(): void
    {
        $request = new ConfirmRequest(question: 'Deploy?');

        $this->assertFalse($request->defaultValue);
    }

    public function testConfirmRequestGetTypeReturnsConfirm(): void
    {
        $request = new ConfirmRequest(question: 'Deploy?');

        $this->assertSame('confirm', $request->getType());
    }

    public function testConfirmResponseHasConfirmedProperty(): void
    {
        $response = new ConfirmResponse(confirmed: true);

        $this->assertTrue($response->confirmed);
    }

    public function testConfirmResponseValueMatchesConfirmed(): void
    {
        $response = new ConfirmResponse(confirmed: true);

        $this->assertSame($response->confirmed, $response->value);
    }

    public function testConfirmRequestJsonSerialization(): void
    {
        $request = new ConfirmRequest(
            question: 'Deploy?',
            defaultValue: true,
            context: 'Production'
        );

        $json = $request->jsonSerialize();

        $this->assertSame(ConfirmRequest::class, $json['type']);
        $this->assertArrayHasKey('correlationId', $json);
        $this->assertSame('Deploy?', $json['data']['question']);
        $this->assertTrue($json['data']['defaultValue']);
        $this->assertSame('Production', $json['data']['context']);
    }

    public function testConfirmResponseJsonSerialization(): void
    {
        $response = new ConfirmResponse(confirmed: true);

        $json = $response->jsonSerialize();

        $this->assertSame(ConfirmResponse::class, $json['type']);
        $this->assertTrue($json['data']['confirmed']);
        $this->assertFalse($json['data']['cancelled']);
    }

    public function testConfirmRequestRoundTripSerialization(): void
    {
        $original = new ConfirmRequest(
            question: 'Deploy?',
            defaultValue: true,
            context: 'Production',
            timeoutMs: 30000
        );

        $json = $original->jsonSerialize();
        $reconstructed = ConfirmRequest::fromJson($json);

        $this->assertSame($original->question, $reconstructed->question);
        $this->assertSame($original->defaultValue, $reconstructed->defaultValue);
        $this->assertSame($original->context, $reconstructed->context);
        $this->assertSame($original->timeoutMs, $reconstructed->timeoutMs);
        $this->assertSame($original->correlationId(), $reconstructed->correlationId());
    }

    public function testCreateResponseWithConfirmation(): void
    {
        $request = new ConfirmRequest(question: 'Deploy?');

        $response = $request->createResponse(
            ConfirmResponse::class,
            ['confirmed' => true, 'cancelled' => false]
        );

        $this->assertInstanceOf(ConfirmResponse::class, $response);
        $this->assertTrue($response->confirmed);
        $this->assertSame($request->correlationId(), $response->correlationId());
    }
}
