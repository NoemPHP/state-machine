<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmResponse;
use PHPUnit\Framework\TestCase;

/**
 * @spec ConfirmResponse.jsonSerialize() includes confirmed and cancelled in data field
 * @see specs/features/interaction.yaml - json-serialization-responses
 */
class ConfirmResponseSerializationTest extends TestCase
{
    public function testSerializesConfirmedAndCancelled(): void
    {
        $response = new ConfirmResponse(confirmed: true, cancelled: false);
        $json = $response->jsonSerialize();

        $this->assertTrue($json['data']['confirmed']);
        $this->assertFalse($json['data']['cancelled']);
    }

    public function testSerializesCancelledResponse(): void
    {
        $response = new ConfirmResponse(confirmed: false, cancelled: true);
        $json = $response->jsonSerialize();

        $this->assertFalse($json['data']['confirmed']);
        $this->assertTrue($json['data']['cancelled']);
    }
}
