<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Chains\Notification;

use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Notify;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Notification chain returns empty array when no listeners registered
 *
 * @see specs/chain/notification.yaml
 */
#[Group('chain')]
#[Group('notification')]
class EmptyArrayDefaultTest extends TestCase
{
    public function testReturnsEmptyArrayWhenNoListeners(): void
    {
        // Arrange
        $chain = new Notification();

        // Act
        $listeners = $chain->call(new Notify(
            $this->createMock(\Noem\State\Region::class),
            new \stdClass()
        ));

        // Assert
        $this->assertIsArray($listeners);
        $this->assertEmpty($listeners);
    }

    public function testReturnsEmptyArrayAfterAllListenersDeregistered(): void
    {
        // Arrange
        $chain = new Notification();
        $deregister1 = $chain->subscribe(fn(object $e) => null);
        $deregister2 = $chain->subscribe(fn(object $e) => null);

        // Act
        $deregister1();
        $deregister2();

        $listeners = $chain->call(new Notify(
            $this->createMock(\Noem\State\Region::class),
            new \stdClass()
        ));

        // Assert
        $this->assertIsArray($listeners);
        $this->assertEmpty($listeners);
    }
}
