<?php

declare(strict_types=1);

namespace Tests\Integration\Feature\Async;

use Noem\State\Feature\Async\AddResolver;
use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Priority;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

final class ResolverAsyncConfigTest extends TestCase
{
    public function testResolverAcceptsAsyncConfig(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $steps = 0;

        // Resolver with AsyncConfig
        $builder->addBuildStep(new AddResolver(
            'userData',
            function () use (&$steps) {
                for ($i = 0; $i < 10; $i++) {
                    $steps++;
                    yield;
                }
                return 'resolved';
            },
            new AsyncConfig(priority: Priority::HIGH)
        ));

        // Access resolver to trigger resolution
        $result = null;
        $builder->onAction('active', function (object $trigger) use (&$result) {
            $result = $this->get('userData');
        });

        $region = $builder
            ->setStates('active')
            ->build();

        // Trigger to access resolver - this starts resolution
        $region->trigger(new \stdClass());

        // Trigger again to let resolver complete (resolvers need time to finish)
        $region->trigger(new \stdClass());

        // HIGH priority = 10 steps per tick
        $this->assertEquals(10, $steps, 'HIGH priority should execute 10 steps');

        // Result may not be available yet as resolver completes async
        // This tests that AsyncConfig is being used, not full resolution
    }

    public function testResolverPriorityAffectsExecution(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $lowSteps = 0;
        $highSteps = 0;

        // LOW priority resolver
        $builder->addBuildStep(new AddResolver(
            'lowPriority',
            function () use (&$lowSteps) {
                for ($i = 0; $i < 10; $i++) {
                    $lowSteps++;
                    yield;
                }
                return 'low-done';
            },
            new AsyncConfig(priority: Priority::LOW)
        ));

        // HIGH priority resolver
        $builder->addBuildStep(new AddResolver(
            'highPriority',
            function () use (&$highSteps) {
                for ($i = 0; $i < 10; $i++) {
                    $highSteps++;
                    yield;
                }
                return 'high-done';
            },
            new AsyncConfig(priority: Priority::HIGH)
        ));

        // Access both resolvers
        $builder->onAction('active', function (object $trigger) {
            $this->get('lowPriority');
            $this->get('highPriority');
        });

        $region = $builder
            ->setStates('active')
            ->build();

        // Trigger to start resolution
        $region->trigger(new \stdClass());

        // HIGH = 10 steps, LOW = 1 step per tick
        $this->assertEquals(1, $lowSteps, 'LOW priority advances 1 step');
        $this->assertEquals(10, $highSteps, 'HIGH priority advances 10 steps');
    }

    public function testResolverWithoutAsyncConfigUsesDefaults(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $steps = 0;

        // Resolver without AsyncConfig (backward compatibility)
        $builder->addBuildStep(new AddResolver(
            'defaultResolver',
            function () use (&$steps) {
                for ($i = 0; $i < 10; $i++) {
                    $steps++;
                    yield;
                }
                return 'resolved';
            }
            // No AsyncConfig - should still work
        ));

        // Access resolver
        $result = null;
        $builder->onAction('active', function (object $trigger) use (&$result) {
            $result = $this->get('defaultResolver');
        });

        $region = $builder
            ->setStates('active')
            ->build();

        $region->trigger(new \stdClass());

        // Should complete (backward compatibility preserved)
        $this->assertGreaterThan(0, $steps, 'Resolver should execute');
    }

    public function testResolverSingletonPreventsRecomputation(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $computations = 0;

        // Resolver with singleton behavior
        $builder->addBuildStep(new AddResolver(
            'singleton',
            function () use (&$computations) {
                $computations++;
                yield;
                return 'computed-once';
            },
            new AsyncConfig(singleton: true, priority: Priority::NORMAL)
        ));

        // Access resolver multiple times
        $builder->onAction('active', function (object $trigger) {
            $val1 = $this->get('singleton');
            $val2 = $this->get('singleton');
            $val3 = $this->get('singleton');
        });

        $region = $builder
            ->setStates('active')
            ->build();

        $region->trigger(new \stdClass());

        // Should only compute once due to caching
        $this->assertEquals(1, $computations, 'Singleton should compute only once');
    }

    public function testMixedResolverConfigurations(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $normalSteps = 0;
        $highSteps = 0;
        $lowSteps = 0;

        $builder->addBuildStep(new AddResolver(
            'normal',
            function () use (&$normalSteps) {
                for ($i = 0; $i < 10; $i++) {
                    $normalSteps++;
                    yield;
                }
                return 'normal';
            },
            new AsyncConfig(priority: Priority::NORMAL)
        ));

        $builder->addBuildStep(new AddResolver(
            'high',
            function () use (&$highSteps) {
                for ($i = 0; $i < 10; $i++) {
                    $highSteps++;
                    yield;
                }
                return 'high';
            },
            new AsyncConfig(priority: Priority::HIGH)
        ));

        $builder->addBuildStep(new AddResolver(
            'low',
            function () use (&$lowSteps) {
                for ($i = 0; $i < 10; $i++) {
                    $lowSteps++;
                    yield;
                }
                return 'low';
            },
            new AsyncConfig(priority: Priority::LOW)
        ));

        // Access all resolvers
        $builder->onAction('active', function (object $trigger) {
            $this->get('normal');
            $this->get('high');
            $this->get('low');
        });

        $region = $builder
            ->setStates('active')
            ->build();

        $region->trigger(new \stdClass());

        // Verify each priority level
        $this->assertEquals(5, $normalSteps, 'NORMAL priority = 5 steps');
        $this->assertEquals(10, $highSteps, 'HIGH priority = 10 steps');
        $this->assertEquals(1, $lowSteps, 'LOW priority = 1 step');
    }
}
