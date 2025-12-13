<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Callbacks;

use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test: Callbacks registered for different regions remain isolated
 *
 * Intent: Prevents callback leakage between region instances, ensuring
 * encapsulation and correct scoping
 */
#[CoversClass(CallbackRegistry::class)]
final class RegionIsolationTest extends TestCase
{
    public function testCallbacksAreIsolatedBetweenRegions(): void
    {
        $registry = new CallbackRegistry();

        // Build first region
        $builder1 = new RegionBuilder();
        $builder1->chainMail->supply(fn(): CallbackRegistry => $registry);
        $callback1 = fn() => 'region1';

        $region1 = $builder1
            ->addState('idle')
            ->markInitial('idle')
            ->onAction('idle', $callback1)
            ->build([]);

        // Build second region
        $builder2 = new RegionBuilder();
        $builder2->chainMail->supply(fn(): CallbackRegistry => $registry);
        $callback2 = fn() => 'region2';

        $region2 = $builder2
            ->addState('idle')
            ->markInitial('idle')
            ->onAction('idle', $callback2)
            ->build([]);

        // Verify region1 callbacks don't leak to region2
        $region1Records = $registry->query(region: $region1);
        $this->assertCount(1, $region1Records);
        $this->assertSame($callback1, $region1Records[0]->callback);

        // Verify region2 callbacks don't leak to region1
        $region2Records = $registry->query(region: $region2);
        $this->assertCount(1, $region2Records);
        $this->assertSame($callback2, $region2Records[0]->callback);
    }

    public function testSharedRegistryStoresMultipleRegions(): void
    {
        $registry = new CallbackRegistry();

        // Build multiple regions with shared registry
        $builder1 = new RegionBuilder();
        $builder1->chainMail->supply(fn(): CallbackRegistry => $registry);
        $callback1 = fn() => 'region1';

        $region1 = $builder1
            ->addState('idle')
            ->markInitial('idle')
            ->onAction('idle', $callback1)
            ->build([]);

        $builder2 = new RegionBuilder();
        $builder2->chainMail->supply(fn(): CallbackRegistry => $registry);
        $callback2 = fn() => 'region2';

        $region2 = $builder2
            ->addState('active')
            ->markInitial('active')
            ->onEnter('active', $callback2)
            ->build([]);

        // Verify registry contains both regions' callbacks
        $allRecords = $registry->query();
        $this->assertCount(2, $allRecords);

        // But querying by region returns only that region's callbacks
        $region1Records = $registry->query(region: $region1);
        $this->assertCount(1, $region1Records);
        $this->assertSame($callback1, $region1Records[0]->callback);

        $region2Records = $registry->query(region: $region2);
        $this->assertCount(1, $region2Records);
        $this->assertSame($callback2, $region2Records[0]->callback);
    }

    public function testSeparateRegistriesProvideCompleteIsolation(): void
    {
        // Build region1 with its own registry
        $registry1 = new CallbackRegistry();
        $builder1 = new RegionBuilder();
        $builder1->chainMail->supply(fn(): CallbackRegistry => $registry1);
        $callback1 = fn() => 'region1';

        $region1 = $builder1
            ->addState('idle')
            ->markInitial('idle')
            ->onAction('idle', $callback1)
            ->build([]);

        // Build region2 with its own registry
        $registry2 = new CallbackRegistry();
        $builder2 = new RegionBuilder();
        $builder2->chainMail->supply(fn(): CallbackRegistry => $registry2);
        $callback2 = fn() => 'region2';

        $region2 = $builder2
            ->addState('idle')
            ->markInitial('idle')
            ->onAction('idle', $callback2)
            ->build([]);

        // Verify complete isolation
        $registry1Records = $registry1->query();
        $this->assertCount(1, $registry1Records);
        $this->assertSame($callback1, $registry1Records[0]->callback);

        $registry2Records = $registry2->query();
        $this->assertCount(1, $registry2Records);
        $this->assertSame($callback2, $registry2Records[0]->callback);

        // Querying registry1 for region2's callbacks returns empty
        $this->assertEmpty($registry1->query(region: $region2));

        // Querying registry2 for region1's callbacks returns empty
        $this->assertEmpty($registry2->query(region: $region1));
    }
}
