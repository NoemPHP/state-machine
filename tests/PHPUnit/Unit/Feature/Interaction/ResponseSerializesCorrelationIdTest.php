<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionResponse.jsonSerialize() includes correlationId field
 * @see specs/features/interaction.yaml - json-serialization-responses
 */
class ResponseSerializesCorrelationIdTest extends TestCase
{
    public function testSerializesCorrelationId(): void
    {
        $response = new ConfirmResponse(confirmed: true);
        $json = $response->jsonSerialize();

        $this->assertArrayHasKey('correlationId', $json);
        $this->assertIsString($json['correlationId']);
        $this->assertSame($response->correlationId(), $json['correlationId']);
    }
}
