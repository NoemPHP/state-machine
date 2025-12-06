<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Chains\Notification;

use Noem\State\Chains\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: NotificationChain stores listeners globally in array
 *
 * @see specs/chain/notification.yaml
 */
#[Group('chain')]
#[Group('notification')]
class GlobalStorageTest extends TestCase
{
    public function testStoresListenersGlobally(): void
    {
        // Arrange
        $chain = new Notification();
        $listener1 = fn($event) => null;
        $listener2 = fn($event) => null;

        // Act
        $chain->subscribe($listener1);
        $chain->subscribe($listener2);

        // Assert - listeners should be retrievable globally
        $listeners = $chain->call(new \Noem\State\Chains\Params\Notify(
            $this->createMock(\Noem\State\Region::class),
            new \stdClass()
        ));

        $this->assertCount(2, $listeners);
        $this->assertContains($listener1, $listeners);
        $this->assertContains($listener2, $listeners);
    }

    public function testStorageIsOutsideRegion(): void
    {
        // Arrange
        $chain = new Notification();
        $listener = fn($event) => null;

        // Act - subscribe without Region reference
        $chain->subscribe($listener);

        // Assert - listener exists in chain, not in any Region
        $listeners = $chain->call(new \Noem\State\Chains\Params\Notify(
            $this->createMock(\Noem\State\Region::class),
            new \stdClass()
        ));

        $this->assertContains($listener, $listeners);
    }
}
