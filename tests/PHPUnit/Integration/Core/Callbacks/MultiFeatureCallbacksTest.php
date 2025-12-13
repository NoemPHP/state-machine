<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Callbacks;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Callbacks\CallbackType;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Feature-specific callback types
class FeatureACallbackType extends CallbackType
{
}

class FeatureBCallbackType extends CallbackType
{
}

/**
 * Test: Multiple features can register different callback types without interference
 *
 * Intent: Ensures callback type system supports feature composition, allowing
 * independent feature operation
 */
#[CoversClass(AddCallback::class)]
#[CoversClass(CallbackRegistry::class)]
final class MultiFeatureCallbacksTest extends TestCase
{
    public function testMultipleFeaturesRegisterIndependently(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $typeA = FeatureACallbackType::get();
        $typeB = FeatureBCallbackType::get();

        $callbackA1 = fn() => 'feature-a-1';
        $callbackA2 = fn() => 'feature-a-2';
        $callbackB1 = fn() => 'feature-b-1';
        $callbackB2 = fn() => 'feature-b-2';

        $region = $builder
            ->addState('idle')
            ->markInitial('idle')
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callbackA1,
                    type: $typeA
                )
            )
            ->addBuildStep(
                new AddCallback(
                    event: 'enter',
                    state: 'idle',
                    callback: $callbackA2,
                    type: $typeA
                )
            )
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callbackB1,
                    type: $typeB
                )
            )
            ->addBuildStep(
                new AddCallback(
                    event: 'exit',
                    state: 'idle',
                    callback: $callbackB2,
                    type: $typeB
                )
            )
            ->build([]);

        // Feature A can retrieve only its callbacks
        $featureARecords = $registry->query(region: $region, type: $typeA);
        $this->assertCount(2, $featureARecords);
        $callbacks = array_map(fn($r) => $r->callback, $featureARecords);
        $this->assertContains($callbackA1, $callbacks);
        $this->assertContains($callbackA2, $callbacks);

        // Feature B can retrieve only its callbacks
        $featureBRecords = $registry->query(region: $region, type: $typeB);
        $this->assertCount(2, $featureBRecords);
        $callbacks = array_map(fn($r) => $r->callback, $featureBRecords);
        $this->assertContains($callbackB1, $callbacks);
        $this->assertContains($callbackB2, $callbacks);
    }

    public function testFeaturesCanFilterByTypeAndEvent(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $typeA = FeatureACallbackType::get();
        $typeB = FeatureBCallbackType::get();

        $callbackA = fn() => 'feature-a-action';
        $callbackB = fn() => 'feature-b-action';

        $region = $builder
            ->addState('idle')
            ->markInitial('idle')
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callbackA,
                    type: $typeA
                )
            )
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callbackB,
                    type: $typeB
                )
            )
            ->build([]);

        // Feature A retrieves only its action callbacks
        $featureAActions = $registry->query(region: $region, type: $typeA, event: 'action');
        $this->assertCount(1, $featureAActions);
        $this->assertSame($callbackA, $featureAActions[0]->callback);

        // Feature B retrieves only its action callbacks
        $featureBActions = $registry->query(region: $region, type: $typeB, event: 'action');
        $this->assertCount(1, $featureBActions);
        $this->assertSame($callbackB, $featureBActions[0]->callback);
    }
}
