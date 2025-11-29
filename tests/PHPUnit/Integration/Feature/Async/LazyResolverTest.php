<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Async resolver computes value only when property accessed
 */
#[Group('async'), Group('integration')]
class LazyResolverTest extends TestCase
{
    public function testResolverComputesValueOnlyWhenPropertyAccessed(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new ExtendedState(),
            new AsyncFeature()
        );

        $resolverExecuted = false;
        $triggerCount = 0;

        $region = $builder
            ->setStates('idle', 'active', 'done')
            ->markInitial('idle')
            ->markFinal('done')
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition(
                'idle',
                'active',
                fn(object $trigger): bool => true
            ))
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition(
                'active',
                'done',
                function(object $trigger) use (&$triggerCount): bool {
                    // Only transition after we've been in active for at least 6 triggers
                    return $triggerCount >= 6;
                }
            ))
            ->onAction('active', function (object $trigger) use (&$triggerCount) {
                $triggerCount++;
            })
            ->onEnter('active', function (object $trigger) {
                // Don't access the lazy property here
            })
            ->onEnter('done', function (object $trigger) {
                // Now access the lazy property
                $value = $this->get('expensive');
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'expensive',
                                    'run' => function () use (&$resolverExecuted) {
                                        $resolverExecuted = true;
                                        yield;
                                        return 'computed-value';
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Trigger to enter active state
        $region->trigger(new \stdClass());
        for ($i = 0; $i < 5; $i++) {
            $region->trigger(new \stdClass());
        }

        // Resolver should NOT have executed yet since we didn't access the property
        $this->assertFalse($resolverExecuted, 'Resolver should not execute until property is accessed');
        
        // Trigger to enter done state where property is accessed
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }
        
        // Now resolver should have executed
        $this->assertTrue($resolverExecuted, 'Resolver should execute when property is accessed');
    }
}
