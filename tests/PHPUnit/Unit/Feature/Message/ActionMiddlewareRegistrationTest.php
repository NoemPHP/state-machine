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
 * Tests that MessageFeature registers middleware on DispatchAction chain
 *
 * Spec: message-dispatch / MessageFeature registers middleware on DispatchAction chain
 * Intent: Enables automatic interception of Message payloads during action dispatch to trigger messaging pipeline
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class ActionMiddlewareRegistrationTest extends TestCase
{
    public function testMessageFeatureRegistersMiddlewareOnDispatchActionChain(): void
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
        $message->then(function () use (&$handlerCalled) {
            $handlerCalled = true;
        });

        // Act - Trigger the message through region (goes through DispatchAction chain)
        $region->trigger($message);

        // Create and deliver response
        $response = $message->createResponse($message::class, []);
        $listeners = $region->notificationChain->call(new \Noem\State\Chains\Params\Notify($region, $response));

        // Manually invoke listeners (call() returns listeners but doesn't execute them)
        foreach ($listeners as $listener) {
            $listener($response, $region);
        }

        // Assert - If middleware is registered, it should set up subscription that delivers the response
        $this->assertTrue($handlerCalled, 'MessageFeature middleware should intercept Message and set up response delivery');
    }
}
