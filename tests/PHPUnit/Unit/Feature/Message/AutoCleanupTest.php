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
 * Tests that temporary subscription auto-unsubscribes after first matching response
 *
 * Spec: correlation-matching / Temporary subscription auto-unsubscribes after first matching response
 * Intent: Implements first-response-wins pattern and prevents memory leaks from persistent subscriptions
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class AutoCleanupTest extends TestCase
{
    public function testTemporarySubscriptionAutoUnsubscribesAfterFirstMatchingResponse(): void
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

        $message->then(function (Message $response) {
        });

        // Act - Dispatch request message
        $region->trigger($message);

        // Get subscriber count after dispatch
        $reflection = new \ReflectionClass($region->notificationChain);
        $subscribersProperty = $reflection->getProperty('listeners');
        $subscribersProperty->setAccessible(true);
        $countAfterDispatch = count($subscribersProperty->getValue($region->notificationChain));

        // Deliver first response
        $response = $message->createResponse($message::class, []);
        $notifyParams = new \Noem\State\Chains\Params\Notify($region, $response);
        $listeners = $region->notificationChain->call($notifyParams);

        // Invoke listeners
        foreach ($listeners as $listener) {
            $listener($response, $region);
        }

        // Check subscriber count after delivery - should decrease
        $countAfterDelivery = count($subscribersProperty->getValue($region->notificationChain));

        // Assert
        $this->assertLessThan($countAfterDispatch, $countAfterDelivery, 'Subscription should be removed after first response delivery');
    }
}
