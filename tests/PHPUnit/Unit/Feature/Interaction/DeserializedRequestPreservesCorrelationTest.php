<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * @spec Deserialized InteractionRequest preserves correlation ID from JSON
 * @see specs/features/interaction.yaml - json-deserialization
 */
class DeserializedRequestPreservesCorrelationTest extends TestCase
{
    public function testPreservesCorrelationId(): void
    {
        $original = new ConfirmRequest(question: 'Test?');
        $originalId = $original->correlationId();

        $json = $original->jsonSerialize();
        $reconstructed = Message::fromJson($json);

        $this->assertSame($originalId, $reconstructed->correlationId());
    }
}
