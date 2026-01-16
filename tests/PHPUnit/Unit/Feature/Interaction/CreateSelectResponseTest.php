<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\SelectRequest;
use Noem\State\Feature\Interaction\SelectResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest.createResponse() creates SelectResponse with inherited correlation ID
 * @see specs/features/interaction.yaml - response-creation
 */
class CreateSelectResponseTest extends TestCase
{
    public function testCreatesSelectResponseWithInheritedCorrelation(): void
    {
        $request = new SelectRequest(question: 'Choose?', options: []);

        $response = $request->createResponse(
            SelectResponse::class,
            ['selectedKey' => 'option1', 'cancelled' => false]
        );

        $this->assertInstanceOf(SelectResponse::class, $response);
        $this->assertSame('option1', $response->selectedKey);
        $this->assertSame($request->correlationId(), $response->correlationId());
    }
}
