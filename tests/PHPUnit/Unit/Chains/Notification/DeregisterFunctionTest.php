<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Chains\Notification;

use Noem\State\Chains\Notification;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: NotificationChain.subscribe() returns deregister function
 *
 * @see specs/chain/notification.yaml
 */
#[Group('chain')]
#[Group('notification')]
class DeregisterFunctionTest extends TestCase
{
    public function testSubscribeReturnsCallable(): void
    {
        // Arrange
        $chain = new Notification();
        $listener = fn(object $event) => null;

        // Act
        $deregister = $chain->subscribe($listener);

        // Assert
        $this->assertIsCallable($deregister);
    }

    public function testDeregisterFunctionIsAClosure(): void
    {
        // Arrange
        $chain = new Notification();
        $listener = fn(object $event) => null;

        // Act
        $deregister = $chain->subscribe($listener);

        // Assert
        $this->assertInstanceOf(\Closure::class, $deregister);
    }
}
