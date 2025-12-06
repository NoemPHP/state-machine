<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Chains\Notification;

use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Notify;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Listeners can be filtered by middleware before being returned
 *
 * @see specs/chain/notification.yaml
 */
#[Group('chain')]
#[Group('notification')]
class MiddlewareCanFilterTest extends TestCase
{
    public function testMiddlewareCanFilterListeners(): void
    {
        // Arrange
        $chain = new Notification();
        $listener1 = fn(object $event) => 'first';
        $listener2 = fn(object $event) => 'second';
        $listener3 = fn(object $event) => 'third';

        $chain->subscribe($listener1);
        $chain->subscribe($listener2);
        $chain->subscribe($listener3);

        // Add middleware that filters to only first listener
        $chain->link(function (Notify $context, callable $next) use ($listener1) {
            $allListeners = $next($context);
            return array_filter($allListeners, fn($l) => $l === $listener1);
        }, prepend: true);

        // Act
        $listeners = $chain->call(new Notify(
            $this->createMock(\Noem\State\Region::class),
            new \stdClass()
        ));

        // Assert
        $this->assertCount(1, $listeners);
        $this->assertContains($listener1, $listeners);
        $this->assertNotContains($listener2, $listeners);
        $this->assertNotContains($listener3, $listeners);
    }

    public function testMiddlewareCanTransformListenerArray(): void
    {
        // Arrange
        $chain = new Notification();
        $chain->subscribe(fn(object $event) => 'a');
        $chain->subscribe(fn(object $event) => 'b');

        // Add middleware that reverses order
        $chain->link(function (Notify $context, callable $next) {
            $listeners = $next($context);
            return array_reverse($listeners);
        }, prepend: true);

        // Act
        $listeners = $chain->call(new Notify(
            $this->createMock(\Noem\State\Region::class),
            new \stdClass()
        ));

        // Assert - order should be reversed
        $this->assertCount(2, $listeners);
        $values = array_map(fn($l) => $l(new \stdClass()), $listeners);
        $this->assertSame(['b', 'a'], $values);
    }
}
