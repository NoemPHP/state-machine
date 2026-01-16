<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ChoiceRequest;
use Noem\State\Feature\Interaction\ChoiceResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest.createResponse() creates ChoiceResponse with inherited correlation ID
 * @see specs/features/interaction.yaml - response-creation
 */
class CreateChoiceResponseTest extends TestCase
{
    public function testCreatesChoiceResponseWithInheritedCorrelation(): void
    {
        $request = new ChoiceRequest(question: 'Select?', options: []);

        $response = $request->createResponse(
            ChoiceResponse::class,
            ['selectedKeys' => ['opt1', 'opt2'], 'cancelled' => false]
        );

        $this->assertInstanceOf(ChoiceResponse::class, $response);
        $this->assertSame(['opt1', 'opt2'], $response->selectedKeys);
        $this->assertSame($request->correlationId(), $response->correlationId());
    }
}
