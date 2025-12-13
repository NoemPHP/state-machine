<?php

declare(strict_types=1);

namespace Tests\Integration\Feature\Async;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Feature\Async\AsyncCallbackType;
use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Priority;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

final class UsesCallbackRegistryTest extends TestCase
{
    public function testAsyncFeatureQueriesRegistryForAsyncCallbacks(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $steps = [];
        $asyncCallback = function (object $trigger) use (&$steps) {
            while (true) {
                yield;
                $steps[] = 'async';
            }
        };

        $config = new AsyncConfig(priority: Priority::LOW);

        // Register callback explicitly as async
        $builder->addBuildStep(
            new AddCallback(
                event: 'action',
                state: 'active',
                callback: $asyncCallback,
                type: AsyncCallbackType::get(),
                metadata: $config
            )
        );

        $region = $builder
            ->setStates('active')
            ->build();

        // Trigger should enqueue and execute async callback
        $event = new \stdClass();
        $region->trigger($event);
        $region->trigger($event);

        $this->assertEquals(['async'], $steps, 'Async callback should be enqueued and executed');
    }

    public function testSyncCallbacksAreNotEnqueued(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $syncExecuted = false;
        $syncCallback = function (object $trigger) use (&$syncExecuted) {
            $syncExecuted = true;
            return 'sync-result';
        };

        // Register as regular callback (no AsyncCallbackType)
        $builder->addBuildStep(
            new AddCallback(
                event: 'action',
                state: 'active',
                callback: $syncCallback
            )
        );

        $region = $builder
            ->setStates('active')
            ->build();

        $event = new \stdClass();
        $region->trigger($event);

        $this->assertTrue($syncExecuted, 'Sync callback should execute normally');
    }

    public function testAsyncConfigFromMetadataIsUsed(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $executions = 0;
        $asyncCallback = function (object $trigger) use (&$executions) {
            while (true) {
                $executions++;
                yield;
            }
        };

        // HIGH priority = 10 steps per tick
        $config = new AsyncConfig(priority: Priority::HIGH);

        $builder->addBuildStep(
            new AddCallback(
                event: 'action',
                state: 'active',
                callback: $asyncCallback,
                type: AsyncCallbackType::get(),
                metadata: $config
            )
        );

        $region = $builder
            ->setStates('active')
            ->build();

        $event = new \stdClass();
        $region->trigger($event);

        // HIGH priority should execute 10 steps
        $this->assertEquals(10, $executions, 'Should use priority from AsyncConfig metadata');
    }

    public function testMultipleAsyncCallbacksWithDifferentConfigs(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $lowExecs = 0;
        $highExecs = 0;

        $lowCallback = function (object $trigger) use (&$lowExecs) {
            while (true) {
                $lowExecs++;
                yield;
            }
        };

        $highCallback = function (object $trigger) use (&$highExecs) {
            while (true) {
                $highExecs++;
                yield;
            }
        };

        $builder->addBuildStep(
            new AddCallback(
                event: 'action',
                state: 'active',
                callback: $lowCallback,
                type: AsyncCallbackType::get(),
                metadata: new AsyncConfig(priority: Priority::LOW)
            )
        );

        $builder->addBuildStep(
            new AddCallback(
                event: 'action',
                state: 'active',
                callback: $highCallback,
                type: AsyncCallbackType::get(),
                metadata: new AsyncConfig(priority: Priority::HIGH)
            )
        );

        $region = $builder
            ->setStates('active')
            ->build();

        $event = new \stdClass();
        $region->trigger($event);

        $this->assertEquals(1, $lowExecs, 'LOW priority = 1 step');
        $this->assertEquals(10, $highExecs, 'HIGH priority = 10 steps');
    }
}
