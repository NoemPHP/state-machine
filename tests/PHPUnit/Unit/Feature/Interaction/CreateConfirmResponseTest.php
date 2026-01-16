<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest.createResponse() creates ConfirmResponse with inherited correlation ID
 * @see specs/features/interaction.yaml - response-creation
 */
class CreateConfirmResponseTest extends TestCase
{
    public function testCreatesConfirmResponseWithInheritedCorrelation(): void
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
