<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that Message.fromJson() falls back to anonymous class when type unknown
 *
 * Spec: json-serialization / Message.fromJson() falls back to anonymous class when type unknown
 * Intent: Provides graceful degradation for unrecognized message types instead of failing
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class AnonymousFallbackTest extends TestCase
{
    public function testFromJsonFallsBackToAnonymousClassWhenTypeUnknown(): void
    {
        $serializedData = [
            'correlationId' => 'test-uuid-123',
            'type' => 'NonExistentMessageClass',
            'data' => (object)['foo' => 'bar']
        ];

        $reconstructed = Message::fromJson($serializedData);

        // Should successfully reconstruct as anonymous Message
        $this->assertInstanceOf(Message::class, $reconstructed);
        $this->assertSame('test-uuid-123', $reconstructed->correlationId());

        // Verify anonymous class structure
        $serialized = $reconstructed->jsonSerialize();
        $this->assertArrayHasKey('type', $serialized);
        $this->assertSame('AnonymousMessage', $serialized['type']);
    }

    public function testFromJsonFallsBackWhenTypeFieldMissing(): void
    {
        $serializedData = [
            'correlationId' => 'test-uuid-456',
            'data' => (object)['baz' => 'qux']
        ];

        $reconstructed = Message::fromJson($serializedData);

        // Should successfully reconstruct as anonymous Message
        $this->assertInstanceOf(Message::class, $reconstructed);
        $this->assertSame('test-uuid-456', $reconstructed->correlationId());
    }
}
