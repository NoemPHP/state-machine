<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test: Features can retrieve CallbackRegistry via ChainMail.get()
 *
 * Intent: Enables features to access centralized callback storage through
 * dependency injection
 */
#[CoversClass(CallbackRegistry::class)]
#[CoversClass(AddCallback::class)]
final class FeatureAccessToRegistryTest extends TestCase
{
    public function testFeatureCanAccessRegistryViaChainMail(): void
    {
        $builder = new RegionBuilder();
        $registry = new CallbackRegistry();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        // Simulate feature accessing registry via ChainMail
        $accessedRegistry = $builder->chainMail->get(CallbackRegistry::class);

        $this->assertSame($registry, $accessedRegistry);
    }

    public function testAddCallbackBuildStepAccessesRegistryCorrectly(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $callback = fn() => 'test';

        $builder
            ->addState('idle')
            ->markInitial('idle')
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callback
                )
            )
            ->build([]);

        // Verify the registry was accessed and callback registered
        $records = $registry->query();
        $this->assertCount(1, $records);
        $this->assertSame($callback, $records[0]->callback);
    }
}
