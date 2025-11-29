<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AsyncFeature initializes ornaments once per region
 */
#[Group('async'), Group('resolver-integration')]
class InitializesOrnamentsOnceTest extends TestCase
{
    public function testInitializesOrnamentsOnce(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new AsyncFeature(),
            new ExtendedState(),
            new \Noem\State\Feature\Transitions\TransitionsFeature()
        );

        $initCount = 0;

        $region = $builder
            ->setStates('idle', 'active')
            ->markInitial('idle')
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition(
                'idle',
                'active',
                fn(object $trigger): bool => true
            ))
            ->onEnter('active', function (object $trigger) use (&$initCount) {
                // First access
                $value1 = $this->get('cached');
                // Second access - should use cached value
                $value2 = $this->get('cached');
                // Third access
                $value3 = $this->get('cached');
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'cached',
                                    'run' => function () use (&$initCount) {
                                        $initCount++;
                                        yield;
                                        return 'value-' . $initCount;
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        
        // Execute ticks
        for ($i = 0; $i < 10; $i++) {
            $region->trigger(new \stdClass());
        }
        
        $this->assertSame(1, $initCount, 'Resolver should only be called once despite multiple accesses');
    }
    
    public function testDoesNotReinitializeOnSubsequentMetaAccess(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(
            new AsyncFeature(),
            new ExtendedState(),
            new \Noem\State\Feature\Transitions\TransitionsFeature()
        );
        
        $resolverCalls = 0;
        
        $region = $builder
            ->setStates('idle', 'step1', 'step2', 'step3')
            ->markInitial('idle')
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition(
                'idle',
                'step1',
                fn(object $trigger): bool => true
            ))
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition(
                'step1',
                'step2',
                fn(object $trigger): bool => true
            ))
            ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition(
                'step2',
                'step3',
                fn(object $trigger): bool => true
            ))
            ->onEnter('step1', function (object $trigger) use (&$resolverCalls) {
                $this->get('value');
            })
            ->onEnter('step2', function (object $trigger) use (&$resolverCalls) {
                $this->get('value');
            })
            ->onEnter('step3', function (object $trigger) use (&$resolverCalls) {
                $this->get('value');
            })
            ->build([
                'loader' => [
                    'array' => [
                        'context' => [
                            'resolvers' => [
                                [
                                    'name' => 'value',
                                    'run' => function () use (&$resolverCalls) {
                                        $resolverCalls++;
                                        yield;
                                        return 'constant';
                                    },
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
        
        // Execute enough ticks to go through all states
        for ($i = 0; $i < 20; $i++) {
            $region->trigger(new \stdClass());
        }
        
        $this->assertSame(1, $resolverCalls, 'Ornament should only be initialized once');
    }
}
