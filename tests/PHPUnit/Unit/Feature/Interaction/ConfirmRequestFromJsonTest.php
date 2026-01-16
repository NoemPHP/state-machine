<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Interaction;

use Noem\State\Feature\Interaction\ConfirmRequest;
use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;

/**
 * @spec Message.fromJson() reconstructs ConfirmRequest from JSON-decoded array
 * @see specs/features/interaction.yaml - json-deserialization
 */
class ConfirmRequestFromJsonTest extends TestCase
{
    public function testReconstructsFromJson(): void
    {
        $original = new ConfirmRequest(
            question: 'Deploy?',
            defaultValue: true,
            context: 'Production',
            timeoutMs: 30000
        );

        $json = $original->jsonSerialize();
        $reconstructed = Message::fromJson($json);

        $this->assertInstanceOf(ConfirmRequest::class, $reconstructed);
        $this->assertSame($original->question, $reconstructed->question);
        $this->assertSame($original->defaultValue, $reconstructed->defaultValue);
        $this->assertSame($original->context, $reconstructed->context);
        $this->assertSame($original->timeoutMs, $reconstructed->timeoutMs);
    }
}
