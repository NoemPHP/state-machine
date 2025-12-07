<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\MessageFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that MessageFeature passes non-Message payloads through unchanged
 *
 * Spec: message-dispatch / MessageFeature passes non-Message payloads through unchanged
 * Intent: Ensures messaging middleware only affects Message instances without interfering with normal action dispatch
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class PassthroughTest extends TestCase
{
    public function testMessageFeaturePassesNonMessagePayloadsThroughUnchanged(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(new MessageFeature())
            ->setStates('idle')
            ->build();

        $normalPayload = new \stdClass();
        $normalPayload->data = 'test';

        // Act - Trigger non-Message payload
        $result = $region->trigger($normalPayload);

        // Assert - Payload should pass through unchanged
        $this->assertSame($normalPayload, $result, 'Non-Message payloads should pass through unchanged');
        $this->assertSame('test', $normalPayload->data, 'Payload properties should remain intact');
    }
}
