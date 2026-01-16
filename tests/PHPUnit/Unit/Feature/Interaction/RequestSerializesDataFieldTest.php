<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest.jsonSerialize() includes data field with request-specific properties
 * @see specs/features/interaction.yaml - json-serialization-requests
 */
class RequestSerializesDataFieldTest extends TestCase
{
    public function testSerializesDataField(): void
    {
        $request = new ConfirmRequest(
            question: 'Test?',
            defaultValue: true,
            context: 'context',
            timeoutMs: 5000
        );

        $json = $request->jsonSerialize();

        $this->assertArrayHasKey('data', $json);
        $this->assertIsArray($json['data']);
        $this->assertArrayHasKey('question', $json['data']);
        $this->assertArrayHasKey('defaultValue', $json['data']);
        $this->assertArrayHasKey('context', $json['data']);
        $this->assertArrayHasKey('timeoutMs', $json['data']);
    }
}
