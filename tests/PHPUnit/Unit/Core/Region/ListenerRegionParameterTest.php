<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Region;

use Noem\State\Chains\Notification;
use Noem\State\Chains\Params\Notify;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Listeners receive Region as optional second parameter
 *
 * @see specs/core/region.yaml
 */
#[Group('region')]
#[Group('notification-subscription')]
class ListenerRegionParameterTest extends TestCase
{
    public function testListenerReceivesRegionAsSecondParameter(): void
    {
        // Arrange
        $receivedEvent = null;
        $receivedRegion = null;

        $region = $this->createRegion();

        $region->on(function (object $event, ?\Noem\State\Region $region = null) use (&$receivedEvent, &$receivedRegion) {
            $receivedEvent = $event;
            $receivedRegion = $region;
        });

        $testEvent = new \stdClass();
        $testEvent->data = 'test';

        // Act - resolve and invoke listeners
        $listeners = $region->notificationChain->call(new Notify($region, $testEvent));
        foreach ($listeners as $listener) {
            $listener($testEvent, $region);
        }

        // Assert
        $this->assertSame($testEvent, $receivedEvent);
        $this->assertSame($region, $receivedRegion);
    }

    public function testListenerCanIdentifySourceRegion(): void
    {
        // Arrange - create regions from same builder so they share NotificationChain
        $builder = new RegionBuilder();
        $builder->setStates('initial', 'final')->markFinal('final');

        $region1 = $builder->build();
        $region2 = $builder->newInstance()->setStates('initial', 'final')->markFinal('final')->build();

        $sourceRegions = [];

        // Both regions share the same notification chain
        $region1->on(function (object $event, ?\Noem\State\Region $region = null) use (&$sourceRegions) {
            $sourceRegions[] = $region;
        });

        $event = new \stdClass();

        // Act - emit from region1
        $listeners = $region1->notificationChain->call(new Notify($region1, $event));
        foreach ($listeners as $listener) {
            $listener($event, $region1);
        }

        // Act - emit from region2 (global listeners will hear it)
        $listeners = $region2->notificationChain->call(new Notify($region2, $event));
        foreach ($listeners as $listener) {
            $listener($event, $region2);
        }

        // Assert - listener can identify which region emitted
        $this->assertCount(2, $sourceRegions);
        $this->assertSame($region1, $sourceRegions[0]);
        $this->assertSame($region2, $sourceRegions[1]);
    }

    public function testRegionParameterIsOptional(): void
    {
        // Arrange
        $region = $this->createRegion();

        $called = false;

        // Listener without Region parameter
        $region->on(function (object $event) use (&$called) {
            $called = true;
        });

        $event = new \stdClass();

        // Act
        $listeners = $region->notificationChain->call(new Notify($region, $event));
        foreach ($listeners as $listener) {
            $listener($event, $region);
        }

        // Assert - should work without error
        $this->assertTrue($called);
    }

    private function createRegion(): Region
    {
        return (new RegionBuilder())
            ->setStates('initial', 'final')
            ->markFinal('final')
            ->build();
    }
}
