<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: RegionLoader builds working region from array configuration
 */
#[Group('loader')]
#[Group('integration')]
class ArrayToRegionTest extends TestCase
{
    public function testBuildsWorkingRegionFromArray(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());
        
        $enterLog = [];
        $exitLog = [];
        $actionLog = [];
        
        $config = [
            'states' => [
                [
                    'name' => 'idle',
                    'transitions' => [
                        [
                            'target' => 'processing',
                            'guard' => fn(object $t): bool => $t->startProcessing ?? false,
                        ],
                    ],
                    'onEnter' => [
                        ['run' => function(object $t) use (&$enterLog) {
                            $enterLog[] = 'idle';
                            return 'idle';
                        }],
                    ],
                    'onExit' => [
                        ['run' => function(object $t) use (&$exitLog) {
                            $exitLog[] = 'idle';
                        }],
                    ],
                ],
                [
                    'name' => 'processing',
                    'transitions' => [
                        [
                            'target' => 'done',
                            'guard' => fn(object $t): bool => $t->complete ?? false,
                        ],
                    ],
                    'onEnter' => [
                        ['run' => function(object $t) use (&$enterLog) {
                            $enterLog[] = 'processing';
                            return 'processing';
                        }],
                    ],
                    'onExit' => [
                        ['run' => function(object $t) use (&$exitLog) {
                            $exitLog[] = 'processing';
                        }],
                    ],
                    'action' => [
                        ['run' => function(object $t) use (&$actionLog) {
                            $actionLog[] = 'work';
                            return 'processing';
                        }],
                    ],
                ],
                [
                    'name' => 'done',
                    'onEnter' => [
                        ['run' => function(object $t) use (&$enterLog) {
                            $enterLog[] = 'done';
                            return 'done';
                        }],
                    ],
                ],
            ],
            'initial' => 'idle',
            'final' => 'done',
        ];
        
        $region = $builder->build([
            'loader' => [
                'array' => $config,
            ],
        ]);
        
        // Verify initial state
        $this->assertTrue($region->isInState('idle'), 'Should start in idle state');
        $this->assertFalse($region->isFinal(), 'Should not be in final state initially');
        $this->assertEmpty($enterLog, 'onEnter not called until first trigger');
        
        // Trigger without guard condition - should stay in idle (this fires initial onEnter)
        $region->trigger(new stdClass());
        $this->assertTrue($region->isInState('idle'), 'Should remain in idle without guard match');
        $this->assertEquals(['idle'], $enterLog, 'Initial onEnter called on first trigger');
        $this->assertCount(0, $actionLog, 'No actions should fire in idle state');
        
        // Transition to processing
        $trigger1 = new stdClass();
        $trigger1->startProcessing = true;
        $region->trigger($trigger1);
        $this->assertTrue($region->isInState('processing'), 'Should transition to processing');
        $this->assertEquals(['idle', 'processing'], $enterLog, 'Should have entered processing');
        $this->assertEquals(['idle'], $exitLog, 'Should have exited idle');
        
        // Trigger action in processing state
        $region->trigger(new stdClass());
        $this->assertTrue($region->isInState('processing'), 'Should remain in processing');
        $this->assertEquals(['work'], $actionLog, 'Action should have been executed');
        
        // Another action
        $region->trigger(new stdClass());
        $this->assertEquals(['work', 'work'], $actionLog, 'Action should execute multiple times');
        
        // Transition to done
        $trigger2 = new stdClass();
        $trigger2->complete = true;
        $region->trigger($trigger2);
        $this->assertTrue($region->isInState('done'), 'Should transition to done');
        $this->assertTrue($region->isFinal(), 'Should be in final state');
        $this->assertEquals(['idle', 'processing', 'done'], $enterLog, 'Should have entered all states');
        $this->assertEquals(['idle', 'processing'], $exitLog, 'Should have exited non-final states');
    }
    
    public function testArrayConfigWithMultipleStates(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        $log = [];

        $config = [
            'states' => [
                [
                    'name' => 'start',
                    'transitions' => [
                        [
                            'target' => 'middle',
                            'guard' => fn(object $t): bool => $t->next ?? false,
                        ],
                    ],
                    'action' => [
                        ['run' => function(object $t) use (&$log) {
                            $log[] = 'start';
                            return 'start';
                        }],
                    ],
                ],
                [
                    'name' => 'middle',
                    'transitions' => [
                        [
                            'target' => 'end',
                            'guard' => fn(object $t): bool => $t->finish ?? false,
                        ],
                    ],
                    'action' => [
                        ['run' => function(object $t) use (&$log) {
                            $log[] = 'middle';
                            return 'middle';
                        }],
                    ],
                ],
                [
                    'name' => 'end',
                ],
            ],
            'initial' => 'start',
            'final' => 'end',
        ];

        $region = $builder->build([
            'loader' => [
                'array' => $config,
            ],
        ]);

        $this->assertTrue($region->isInState('start'));
        $this->assertFalse($region->isFinal());

        // Trigger action in start
        $region->trigger(new stdClass());
        $this->assertEquals(['start'], $log);
        $this->assertTrue($region->isInState('start'));

        // Transition to middle (action fires before transition evaluation)
        $trigger1 = new stdClass();
        $trigger1->next = true;
        $region->trigger($trigger1);
        $this->assertTrue($region->isInState('middle'));
        $this->assertEquals(['start', 'start'], $log, 'Action in start fires before transition');

        // Trigger action in middle
        $region->trigger(new stdClass());
        $this->assertEquals(['start', 'start', 'middle'], $log);

        // Transition to end (action fires before transition evaluation)
        $trigger2 = new stdClass();
        $trigger2->finish = true;
        $region->trigger($trigger2);
        $this->assertTrue($region->isInState('end'));
        $this->assertTrue($region->isFinal());
        $this->assertEquals(['start', 'start', 'middle', 'middle'], $log, 'Action in middle fires before transition');
    }
}
