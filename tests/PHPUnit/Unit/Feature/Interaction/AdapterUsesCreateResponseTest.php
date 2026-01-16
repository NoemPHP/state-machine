<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec Framework adapters create responses via InteractionRequest.createResponse()
 * @see specs/features/interaction.yaml - framework-adapter-response-creation
 */
class AdapterUsesCreateResponseTest extends TestCase
{
    public function testAdapterUsesCreateResponse(): void
    {
        $request = new ConfirmRequest(question: 'Test?');

        // Adapter creates response using createResponse()
        $response = $request->createResponse(
            ConfirmResponse::class,
            ['confirmed' => true, 'cancelled' => false]
        );

        $this->assertInstanceOf(ConfirmResponse::class, $response);
        $this->assertSame($request->correlationId(), $response->correlationId());
    }
}
