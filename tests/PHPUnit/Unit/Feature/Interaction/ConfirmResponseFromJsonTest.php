<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmResponse;
use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * @spec Message.fromJson() reconstructs ConfirmResponse from JSON-decoded array
 * @see specs/features/interaction.yaml - json-deserialization
 */
class ConfirmResponseFromJsonTest extends TestCase
{
    public function testReconstructsFromJson(): void
    {
        $original = new ConfirmResponse(confirmed: true, cancelled: false);

        $json = $original->jsonSerialize();
        $reconstructed = Message::fromJson($json);

        $this->assertInstanceOf(ConfirmResponse::class, $reconstructed);
        $this->assertSame($original->confirmed, $reconstructed->confirmed);
        $this->assertSame($original->cancelled, $reconstructed->cancelled);
    }
}
