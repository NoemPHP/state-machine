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
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that temporary subscription ignores non-Message events
 *
 * Spec: correlation-matching / Temporary subscription ignores non-Message events
 * Intent: Ensures message pipeline only processes Message instances without interfering with other event types
 * Criticality: contract
 */

#[Group('feature')]
#[Group('message')]
class NonMessageIgnoredTest extends TestCase
{
    public function testTemporarySubscriptionIgnoresNonMessageEvents(): void
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

        $handlerCalled = false;

        $message->then(function (Message $response) use (&$handlerCalled) {
            $handlerCalled = true;
        });

        // Act - Dispatch request message
        $region->trigger($message);

        // Notify with non-Message event
        $nonMessageEvent = new \stdClass();
        $notifyParams = new \Noem\State\Chains\Params\Notify($region, $nonMessageEvent);
        $region->notificationChain->call($notifyParams);

        // Assert
        $this->assertFalse($handlerCalled, 'Handler should NOT be called for non-Message events');
    }
}
