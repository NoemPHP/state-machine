<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Chains\Notification;

use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Notify;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Notification chain provider returns all listeners globally
 *
 * @see specs/chain/notification.yaml
 */
#[Group('chain')]
#[Group('notification')]
class ProviderReturnsAllListenersTest extends TestCase
{
    public function testProviderReturnsAllListenersGlobally(): void
    {
        // Arrange
        $chain = new Notification();
        $listener1 = fn(object $event) => 'first';
        $listener2 = fn(object $event) => 'second';

        $chain->subscribe($listener1);
        $chain->subscribe($listener2);

        // Act
        $listeners = $chain->call(new Notify(
            $this->createMock(\Noem\State\Region::class),
            new \stdClass()
        ));

        // Assert - all listeners returned regardless of Region
        $this->assertCount(2, $listeners);
        $this->assertSame([$listener1, $listener2], $listeners);
    }

    public function testSameListenersReturnedRegardlessOfRegion(): void
    {
        // Arrange
        $chain = new Notification();
        $listener = fn(object $event) => null;
        $chain->subscribe($listener);

        $region1 = $this->createMock(\Noem\State\Region::class);
        $region2 = $this->createMock(\Noem\State\Region::class);

        // Act
        $listeners1 = $chain->call(new Notify($region1, new \stdClass()));
        $listeners2 = $chain->call(new Notify($region2, new \stdClass()));

        // Assert - same listeners for different regions (global)
        $this->assertSame($listeners1, $listeners2);
        $this->assertContains($listener, $listeners1);
        $this->assertContains($listener, $listeners2);
    }
}
