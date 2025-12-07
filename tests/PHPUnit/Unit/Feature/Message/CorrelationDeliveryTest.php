<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that temporary subscription delivers responses with matching correlation ID to then() handlers
 *
 * Spec: correlation-matching / Temporary subscription delivers responses with matching correlation ID to then() handlers
 * Intent: Completes request-response cycle by routing correlated responses to registered handlers automatically
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class CorrelationDeliveryTest extends TestCase
{
    public function testTemporarySubscriptionDeliversResponsesWithMatchingCorrelationIdToThenHandlers(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(
                new SubscriptionFeature(),
                new MessageFeature()
            )
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

        $handlerCalled = false;
        $receivedResponse = null;

        $message->then(function (Message $response) use (&$handlerCalled, &$receivedResponse) {
            $handlerCalled = true;
            $receivedResponse = $response;
        });

        // Act - Dispatch request message
        $region->trigger($message);

        // Create and notify response with matching correlation ID
        $response = $message->createResponse($message::class, []);
        $notifyParams = new Notify($region, $response);
        $listeners = $region->notificationChain->call($notifyParams);

        // Invoke listeners
        foreach ($listeners as $listener) {
            $listener($response, $region);
        }

        // Assert
        $this->assertTrue($handlerCalled, 'Handler should be called when response with matching correlation ID is delivered');
        $this->assertSame($response, $receivedResponse, 'Handler should receive the response object');
    }
}
