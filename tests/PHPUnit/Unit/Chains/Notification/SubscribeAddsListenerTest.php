<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Chains\Notification;

use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Notify;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: NotificationChain.subscribe() adds listener to global array
 *
 * @see specs/chain/notification.yaml
 */
#[Group('chain')]
#[Group('notification')]
class SubscribeAddsListenerTest extends TestCase
{
    public function testSubscribeAddsListener(): void
    {
        // Arrange
        $chain = new Notification();
        $listener = fn(object $event) => null;

        // Act
        $chain->subscribe($listener);

        // Assert
        $listeners = $chain->call(new Notify(
            $this->createMock(\Noem\State\Region::class),
            new \stdClass()
        ));

        $this->assertContains($listener, $listeners);
    }

    public function testSubscribeAddsMultipleListeners(): void
    {
        // Arrange
        $chain = new Notification();
        $listener1 = fn(object $event) => 'first';
        $listener2 = fn(object $event) => 'second';
        $listener3 = fn(object $event) => 'third';

        // Act
        $chain->subscribe($listener1);
        $chain->subscribe($listener2);
        $chain->subscribe($listener3);

        // Assert
        $listeners = $chain->call(new Notify(
            $this->createMock(\Noem\State\Region::class),
            new \stdClass()
        ));

        $this->assertCount(3, $listeners);
        $this->assertContains($listener1, $listeners);
        $this->assertContains($listener2, $listeners);
        $this->assertContains($listener3, $listeners);
    }
}
