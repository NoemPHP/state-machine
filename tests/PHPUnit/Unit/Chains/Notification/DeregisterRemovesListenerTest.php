<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Chains\Notification;

use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Notify;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Deregister function removes listener from global array
 *
 * @see specs/chain/notification.yaml
 */
#[Group('chain')]
#[Group('notification')]
class DeregisterRemovesListenerTest extends TestCase
{
    public function testDeregisterRemovesListener(): void
    {
        // Arrange
        $chain = new Notification();
        $listener = fn(object $event) => null;
        $deregister = $chain->subscribe($listener);

        // Act
        $deregister();

        // Assert
        $listeners = $chain->call(new Notify(
            $this->createMock(\Noem\State\Region::class),
            new \stdClass()
        ));

        $this->assertNotContains($listener, $listeners);
    }

    public function testDeregisterOnlyRemovesSpecificListener(): void
    {
        // Arrange
        $chain = new Notification();
        $listener1 = fn(object $event) => 'first';
        $listener2 = fn(object $event) => 'second';
        $listener3 = fn(object $event) => 'third';

        $chain->subscribe($listener1);
        $deregister2 = $chain->subscribe($listener2);
        $chain->subscribe($listener3);

        // Act
        $deregister2();

        // Assert
        $listeners = $chain->call(new Notify(
            $this->createMock(\Noem\State\Region::class),
            new \stdClass()
        ));

        $this->assertCount(2, $listeners);
        $this->assertContains($listener1, $listeners);
        $this->assertNotContains($listener2, $listeners);
        $this->assertContains($listener3, $listeners);
    }

    public function testDeregisterCanBeCalledMultipleTimes(): void
    {
        // Arrange
        $chain = new Notification();
        $listener = fn(object $event) => null;
        $deregister = $chain->subscribe($listener);

        // Act & Assert - should not throw
        $deregister();
        $deregister(); // Should be safe to call again

        $this->assertTrue(true); // No exception thrown
    }
}
