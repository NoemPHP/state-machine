<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionResponse.jsonSerialize() includes data field with response-specific properties
 * @see specs/features/interaction.yaml - json-serialization-responses
 */
class ResponseSerializesDataFieldTest extends TestCase
{
    public function testSerializesDataField(): void
    {
        $response = new ConfirmResponse(confirmed: true, cancelled: false);
        $json = $response->jsonSerialize();

        $this->assertArrayHasKey('data', $json);
        $this->assertIsArray($json['data']);
        $this->assertArrayHasKey('confirmed', $json['data']);
        $this->assertArrayHasKey('cancelled', $json['data']);
    }
}
