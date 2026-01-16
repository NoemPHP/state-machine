<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\PromptRequest;
use Noem\State\Feature\Interaction\PromptResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest.createResponse() creates PromptResponse with inherited correlation ID
 * @see specs/features/interaction.yaml - response-creation
 */
class CreatePromptResponseTest extends TestCase
{
    public function testCreatesPromptResponseWithInheritedCorrelation(): void
    {
        $request = new PromptRequest(question: 'Enter text?');

        $response = $request->createResponse(
            PromptResponse::class,
            ['input' => 'User input', 'cancelled' => false]
        );

        $this->assertInstanceOf(PromptResponse::class, $response);
        $this->assertSame('User input', $response->input);
        $this->assertSame($request->correlationId(), $response->correlationId());
    }
}
