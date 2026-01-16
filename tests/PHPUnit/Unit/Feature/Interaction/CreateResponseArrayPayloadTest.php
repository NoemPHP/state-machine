<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Interaction\ConfirmResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest.createResponse() accepts array payload for response data
 * @see specs/features/interaction.yaml - response-creation
 */
class CreateResponseArrayPayloadTest extends TestCase
{
    public function testAcceptsArrayPayload(): void
    {
        $request = new ConfirmRequest(question: 'Test?');

        $response = $request->createResponse(
            ConfirmResponse::class,
            [
                'confirmed' => true,
                'cancelled' => false
            ]
        );

        $this->assertInstanceOf(ConfirmResponse::class, $response);
        $this->assertTrue($response->confirmed);
        $this->assertFalse($response->cancelled);
    }
}
