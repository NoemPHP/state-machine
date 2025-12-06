<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Subscription;

use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class TestEvent
{
    public string $data = 'test';
}

class OtherEvent
{
    public int $value = 42;
}

/**
 * Acceptance Criterion: Feature filters listeners by parameter type compatibility
 *
 * @see specs/features/subscription.yaml
 */
#[Group('feature')]
#[Group('subscription')]
class TypeFilterTest extends TestCase
{
    public function testFiltersListenersByParameterType(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(new SubscriptionFeature())
            ->setStates('idle')
            ->build();

        $testEventCalled = false;
        $otherEventCalled = false;

        $region->on(function (TestEvent $e) use (&$testEventCalled) {
            $testEventCalled = true;
        });

        $region->on(function (OtherEvent $e) use (&$otherEventCalled) {
            $otherEventCalled = true;
        });

        // Act - emit TestEvent
        $listeners = $region->notificationChain->call(new Notify($region, new TestEvent()));

        // Assert - only TestEvent listener returned
        $this->assertCount(1, $listeners);

        foreach ($listeners as $listener) {
            $listener(new TestEvent(), $region);
        }

        $this->assertTrue($testEventCalled);
        $this->assertFalse($otherEventCalled);
    }

    public function testReturnsOnlyCompatibleListeners(): void
    {
        // Arrange
        $region = (new RegionBuilder())
            ->enableFeatures(new SubscriptionFeature())
            ->setStates('idle')
            ->build();

        $listener1 = fn(TestEvent $e) => 'test';
        $listener2 = fn(OtherEvent $e) => 'other';
        $listener3 = fn(TestEvent $e) => 'test2';

        $region->on($listener1);
        $region->on($listener2);
        $region->on($listener3);

        // Act
        $listeners = $region->notificationChain->call(new Notify($region, new OtherEvent()));

        // Assert - only OtherEvent listener
        $this->assertCount(1, $listeners);
        $this->assertContains($listener2, $listeners);
        $this->assertNotContains($listener1, $listeners);
        $this->assertNotContains($listener3, $listeners);
    }
}
