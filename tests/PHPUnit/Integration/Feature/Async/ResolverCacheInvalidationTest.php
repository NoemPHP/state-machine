<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Integration\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Resolver cache invalidates and recomputes on dependency change
 */
#[Group('async'), Group('integration')]
class ResolverCacheInvalidationTest extends TestCase
{
    public function testResolverCacheInvalidatesOnDependencyChange(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new ExtendedState(),
            new AsyncFeature(),
            new TransitionsFeature()
        );
        
        $computationCount = 0;
        $results = [];
        
        $region = $builder
            ->setStates('idle', 'step1', 'step2', 'step3')
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition(
                'idle',
                'step1',
                fn(object $t): bool => true
            ))
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition(
                'step1',
                'step2',
                fn(object $t): bool => true
            ))
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition(
                'step2',
                'step3',
                fn(object $t): bool => true
            ))
            ->onEnter('step1', function (object $trigger) {
                // Set initial dependency value
                $this->set('dependency', 'value1');
                // Trigger resolver computation
                $this->get('computed');
            })
            ->onEnter('step2', function (object $trigger) use (&$results) {
                // Access again - should use cached value from step1
                $results['step2'] = $this->get('computed');
            })
            ->onEnter('step3', function (object $trigger) use (&$results) {
                // Change dependency - should invalidate cache
                $this->set('dependency', 'value2');
                // Access again - should trigger recomputation
                $results['step3'] = $this->get('computed');
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'computed',
                                    'run' => function () use (&$computationCount) {
                                        $computationCount++;
                                        yield;
                                        $dep = $this->get('dependency');
                                        return 'prefix-' . $dep;
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        
        // Execute through all states and allow async operations to complete
        for ($i = 0; $i < 30; $i++) {
            $region->trigger(new \stdClass());
        }
        
        // Resolver should have computed exactly twice: once for value1, once for value2 after invalidation
        $this->assertSame(2, $computationCount, 'Resolver should recompute after dependency change');
    }
}
