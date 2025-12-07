<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Message;

use Noem\State\Middleware\ChainMail;
use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Dispatch;
use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\Message\Message;
use Noem\State\Feature\Message\MessageFeature;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that second response with same correlation ID does not trigger then() handlers
 *
 * Spec: correlation-matching / Second response with same correlation ID does not trigger then() handlers
 * Intent: Enforces single-response semantics by ignoring duplicate responses after subscription cleanup
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class DuplicateResponseIgnoredTest extends TestCase
{
    public function testSecondResponseWithSameCorrelationIdDoesNotTriggerThenHandlers(): void
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

        $callCount = 0;

        $message->then(function (Message $response) use (&$callCount) {
            $callCount++;
        });

        // Act - Dispatch request message
        $region->trigger($message);

        // Deliver first response
        $response1 = $message->createResponse($message::class, []);
        $notifyParams1 = new \Noem\State\Chains\Params\Notify($region, $response1);
        $listeners1 = $region->notificationChain->call($notifyParams1);

        // Invoke listeners
        foreach ($listeners1 as $listener) {
            $listener($response1, $region);
        }

        // Deliver second response with same correlation ID
        $response2 = $message->createResponse($message::class, []);
        $notifyParams2 = new \Noem\State\Chains\Params\Notify($region, $response2);
        $listeners2 = $region->notificationChain->call($notifyParams2);

        // Invoke listeners (should be empty after cleanup)
        foreach ($listeners2 as $listener) {
            $listener($response2, $region);
        }

        // Assert
        $this->assertSame(1, $callCount, 'Handler should only be called once, ignoring duplicate responses');
    }
}
