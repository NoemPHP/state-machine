<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec InteractionResponse.jsonSerialize() includes type field with fully qualified class name
 * @see specs/features/interaction.yaml - json-serialization-responses
 */
class ResponseSerializesTypeTest extends TestCase
{
    public function testSerializesTypeFQCN(): void
    {
        $response = new ConfirmResponse(confirmed: true);
        $json = $response->jsonSerialize();

        $this->assertArrayHasKey('type', $json);
        $this->assertSame(ConfirmResponse::class, $json['type']);
    }
}
