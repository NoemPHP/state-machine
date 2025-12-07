<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that MessageFeature creates temporary subscription when Message dispatched
 *
 * Spec: correlation-matching / MessageFeature creates temporary subscription when Message dispatched
 * Intent: Sets up correlation-based listener immediately on message dispatch to capture matching responses
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class TemporarySubscriptionTest extends TestCase
{
    public function testMessageFeatureCreatesTemporarySubscriptionWhenMessageDispatched(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(new MessageFeature())
            ->setStates('idle')
            ->build();

        $message = new class extends Message {
            public function __construct(?string $correlationId = null)
            {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed
            {
                return ['correlationId' => $this->correlationId, 'type' => 'Test', 'data' => []];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new static($correlationId);
            }
        };

        // Get initial subscriber count
        $reflection = new \ReflectionClass($region->notificationChain);
        $subscribersProperty = $reflection->getProperty('listeners');
        $subscribersProperty->setAccessible(true);
        $initialCount = count($subscribersProperty->getValue($region->notificationChain));

        // Act - Dispatch message
        $region->trigger($message);

        // Assert - Verify subscription was created
        $afterCount = count($subscribersProperty->getValue($region->notificationChain));
        $this->assertGreaterThan($initialCount, $afterCount, 'MessageFeature should create subscription when Message is dispatched');
    }
}
