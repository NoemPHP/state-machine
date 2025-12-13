<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Priority;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Resolver tasks execute with configured priority
 * Intent: Critical resolvers execute more frequently ensuring responsive data access
 */
#[Group('async'), Group('integration'), Group('resolver')]
class ResolverPriorityTest extends TestCase
{
    public function testResolverTasksExecuteWithConfiguredPriority(): void
    {
        // RED TEST: Resolver feature not yet fully implemented
        // This test will fail until AddResolver BuildStep supports AsyncConfig

        $this->markTestIncomplete(
            'Resolver priority test awaiting AddResolver AsyncConfig implementation. ' .
            'When implemented, this should test that resolvers with HIGH priority ' .
            'execute more steps per tick than LOW priority resolvers.'
        );

        /*
        $builder = new RegionBuilder();
        $builder->enableFeatures(new ExtendedState(), new AsyncFeature());

        $highPrioritySteps = 0;
        $lowPrioritySteps = 0;

        $highResolver = function ($context) use (&$highPrioritySteps) {
            for ($i = 0; $i < 20; $i++) {
                $highPrioritySteps++;
                yield;
            }
            return 'high_result';
        };

        $lowResolver = function ($context) use (&$lowPrioritySteps) {
            for ($i = 0; $i < 20; $i++) {
                $lowPrioritySteps++;
                yield;
            }
            return 'low_result';
        };

        $highConfig = new AsyncConfig(priority: Priority::HIGH);
        $lowConfig = new AsyncConfig(priority: Priority::LOW);

        $builder->addResolver('highProp', $highResolver, $highConfig);
        $builder->addResolver('lowProp', $lowResolver, $lowConfig);

        $region = $builder->build();

        // Access both properties to start resolvers
        $context = $region->getContext();
        $context['highProp'];
        $context['lowProp'];

        // After one tick, HIGH priority should have advanced more steps
        $this->assertGreaterThan($lowPrioritySteps, $highPrioritySteps);
        */
    }
}
