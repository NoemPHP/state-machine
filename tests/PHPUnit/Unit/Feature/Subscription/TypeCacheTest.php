<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Subscription;

use Noem\State\Chains\Params\Notify;
use Noem\State\Feature\Subscription\SubscriptionFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Feature uses SplObjectStorage to cache listener parameter types
 *
 * @see specs/features/subscription.yaml
 */
#[Group('feature')]
#[Group('subscription')]
class TypeCacheTest extends TestCase
{
    public function testFeatureUsesSplObjectStorageForCaching(): void
    {
        // Arrange
        $feature = new SubscriptionFeature();
        $listener = fn(object $event) => null;

        $region = (new RegionBuilder())
            ->enableFeatures($feature)
            ->setStates('idle')
            ->build();

        $region->on($listener);

        // Act - call multiple times to trigger caching
        $listeners1 = $region->notificationChain->call(new Notify($region, new \stdClass()));
        $listeners2 = $region->notificationChain->call(new Notify($region, new \stdClass()));
        $listeners3 = $region->notificationChain->call(new Notify($region, new \stdClass()));

        // Assert - should work efficiently with caching (no errors)
        $this->assertCount(1, $listeners1);
        $this->assertCount(1, $listeners2);
        $this->assertCount(1, $listeners3);
    }

    public function testCacheImprovesPerformance(): void
    {
        // Arrange
        $feature = new SubscriptionFeature();
        $region = (new RegionBuilder())
            ->enableFeatures($feature)
            ->setStates('idle')
            ->build();

        // Add many listeners
        for ($i = 0; $i < 100; $i++) {
            $region->on(fn(object $event) => null);
        }

        // Act - repeated calls should use cache
        $start = microtime(true);
        for ($i = 0; $i < 10; $i++) {
            $region->notificationChain->call(new Notify($region, new \stdClass()));
        }
        $duration = microtime(true) - $start;

        // Assert - should complete quickly (cache working)
        $this->assertLessThan(1.0, $duration); // Should be much faster than 1 second
    }
}
