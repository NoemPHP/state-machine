<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionRequest.jsonSerialize() includes correlationId field
 * @see specs/features/interaction.yaml - json-serialization-requests
 */
class RequestSerializesCorrelationIdTest extends TestCase
{
    public function testSerializesCorrelationId(): void
    {
        $request = new ConfirmRequest(question: 'Test?');
        $json = $request->jsonSerialize();

        $this->assertArrayHasKey('correlationId', $json);
        $this->assertIsString($json['correlationId']);
        $this->assertSame($request->correlationId(), $json['correlationId']);
    }
}
