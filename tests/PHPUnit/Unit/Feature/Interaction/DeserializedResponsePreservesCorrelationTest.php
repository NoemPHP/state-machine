<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * @spec Deserialized InteractionResponse preserves correlation ID from JSON
 * @see specs/features/interaction.yaml - json-deserialization
 */
class DeserializedResponsePreservesCorrelationTest extends TestCase
{
    public function testPreservesCorrelationId(): void
    {
        $correlationId = 'test-correlation-id';
        $original = new ConfirmResponse(confirmed: true, correlationId: $correlationId);

        $json = $original->jsonSerialize();
        $reconstructed = Message::fromJson($json);

        $this->assertSame($correlationId, $reconstructed->correlationId());
    }
}
