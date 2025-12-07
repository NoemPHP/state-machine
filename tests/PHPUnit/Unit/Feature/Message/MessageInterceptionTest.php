<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that MessageFeature intercepts Message payloads in DispatchAction chain
 *
 * Spec: message-dispatch / MessageFeature intercepts Message payloads in DispatchAction chain
 * Intent: Detects when dispatched actions contain Message instances and routes them through correlation pipeline
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class MessageInterceptionTest extends TestCase
{
    public function testMessageFeatureInterceptsMessagePayloadsInActionChain(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new SubscriptionFeature(),
                new MessageFeature()
            )
            ->setStates('idle')
            ->build();

        // Create a concrete Message implementation for testing
        $message = new class extends Message {
            public function __construct(?string $correlationId = null)
            {
                parent::__construct($correlationId);
            }

            public function jsonSerialize(): mixed
            {
                return [
                    'correlationId' => $this->correlationId,
                    'type' => 'TestMessage',
                    'data' => []
                ];
            }

            protected static function fromData(mixed $data, ?string $correlationId): static
            {
                return new static($correlationId);
            }
        };

        $handlerCalled = false;
        $message->then(function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        // Act - Trigger message (MessageFeature should intercept and set up subscription)
        $region->trigger($message);

        // Deliver response
        $response = $message->createResponse($message::class, []);
        $listeners = $region->notificationChain->call(new \Noem\State\Chains\Params\Notify($region, $response));

        // Invoke listeners
        foreach ($listeners as $listener) {
            $listener($response, $region);
        }

        // Assert - Handler being called proves interception occurred
        $this->assertTrue($handlerCalled, 'MessageFeature should intercept Message payloads and set up response delivery');
    }
}
