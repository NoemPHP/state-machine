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
 * Acceptance Criterion: RegionLoader spawns sub-regions based on triggers
 */
#[Group('loader')]
#[Group('integration')]
class DynamicSpawningTest extends TestCase
{
    public function testSpawnsSubRegionFromYaml(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());
        
        // Use globals to track child actions
        $GLOBALS['child_actions'] = 0;
        $GLOBALS['spawn_count'] = 0;
        
        $yaml = <<<'YAML'
        states:
          - name: parent
            spawn:
              - guard: !php "function(object $t): bool { $GLOBALS['spawn_count']++; return $GLOBALS['spawn_count'] === 1; }"
                region:
                  states:
                    - name: child
                      action:
                        - run: !php "function(object $t) { $GLOBALS['child_actions']++; return 'child'; }"
                  initial: child
        initial: parent
        YAML;
        
        $helpers = [
            'php' => fn(string $code) => eval("return $code;"),
        ];
        
        $region = $builder->build([
            'loader' => [
                'yaml' => $yaml,
                'yamlHelpers' => $helpers,
            ],
        ]);
        
        $this->assertTrue($region->isInState('parent'));
        $this->assertEquals(0, $GLOBALS['child_actions']);
        
        // First trigger spawns the child
        $region->trigger(new stdClass());
        $this->assertEquals(1, $GLOBALS['spawn_count'], 'Guard should be called');
        $this->assertEquals(1, $GLOBALS['child_actions'], 'Child should receive spawn trigger');
        
        // Second trigger should propagate to child (no new spawn)
        $region->trigger(new stdClass());
        $this->assertEquals(2, $GLOBALS['spawn_count'], 'Guard called again');
        $this->assertEquals(2, $GLOBALS['child_actions'], 'Child receives subsequent triggers');
        
        // Cleanup
        unset($GLOBALS['child_actions'], $GLOBALS['spawn_count']);
    }
    
    public function testSpawnsSubRegionFromArray(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());
        
        $childActions = 0;
        $spawnCount = 0;
        
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => function(object $t) use (&$spawnCount): bool {
                                $spawnCount++;
                                return $spawnCount === 1;
                            },
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child',
                                        'action' => [
                                            ['run' => function(object $t) use (&$childActions) {
                                                $childActions++;
                                                return 'child';
                                            }],
                                        ],
                                    ],
                                ],
                                'initial' => 'child',
                            ],
                        ],
                    ],
                ],
            ],
            'initial' => 'parent',
        ];
        
        $region = $builder->build([
            'loader' => [
                'array' => $config,
            ],
        ]);
        
        $this->assertTrue($region->isInState('parent'));
        $this->assertEquals(0, $childActions);
        
        // First trigger spawns the child
        $region->trigger(new stdClass());
        $this->assertEquals(1, $spawnCount, 'Guard should be called');
        $this->assertEquals(1, $childActions, 'Child should receive spawn trigger');
        
        // Second trigger should propagate to child (no new spawn)
        $region->trigger(new stdClass());
        $this->assertEquals(2, $spawnCount, 'Guard called again');
        $this->assertEquals(2, $childActions, 'Child receives subsequent triggers');
    }
    
    public function testConditionalSpawningBasedOnTrigger(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());
        
        $childActions = 0;
        
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn(object $t): bool => $t->shouldSpawn ?? false,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child',
                                        'action' => [
                                            ['run' => function(object $t) use (&$childActions) {
                                                $childActions++;
                                                return 'child';
                                            }],
                                        ],
                                    ],
                                ],
                                'initial' => 'child',
                            ],
                        ],
                    ],
                ],
            ],
            'initial' => 'parent',
        ];
        
        $region = $builder->build([
            'loader' => [
                'array' => $config,
            ],
        ]);
        
        // Trigger without spawn condition - no spawn
        $region->trigger(new stdClass());
        $this->assertEquals(0, $childActions, 'Child not spawned without condition');
        
        // Trigger with spawn condition
        $spawnTrigger = new stdClass();
        $spawnTrigger->shouldSpawn = true;
        $region->trigger($spawnTrigger);
        $this->assertEquals(1, $childActions, 'Child spawned and receives trigger');
        
        // Subsequent triggers propagate to spawned child
        $region->trigger(new stdClass());
        $this->assertEquals(2, $childActions, 'Spawned child receives subsequent triggers');
    }
    
    public function testMultipleSpawnDefinitions(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());
        
        $child1Actions = 0;
        $child2Actions = 0;
        
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn(object $t): bool => $t->spawnChild1 ?? false,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child1',
                                        'action' => [
                                            ['run' => function(object $t) use (&$child1Actions) {
                                                $child1Actions++;
                                                return 'child1';
                                            }],
                                        ],
                                    ],
                                ],
                                'initial' => 'child1',
                            ],
                        ],
                        [
                            'guard' => fn(object $t): bool => $t->spawnChild2 ?? false,
                            'region' => [
                                'states' => [
                                    [
                                        'name' => 'child2',
                                        'action' => [
                                            ['run' => function(object $t) use (&$child2Actions) {
                                                $child2Actions++;
                                                return 'child2';
                                            }],
                                        ],
                                    ],
                                ],
                                'initial' => 'child2',
                            ],
                        ],
                    ],
                ],
            ],
            'initial' => 'parent',
        ];
        
        $region = $builder->build([
            'loader' => [
                'array' => $config,
            ],
        ]);
        
        // Spawn first child
        $trigger1 = new stdClass();
        $trigger1->spawnChild1 = true;
        $region->trigger($trigger1);
        $this->assertEquals(1, $child1Actions);
        $this->assertEquals(0, $child2Actions);
        
        // Spawn second child
        $trigger2 = new stdClass();
        $trigger2->spawnChild2 = true;
        $region->trigger($trigger2);
        $this->assertEquals(2, $child1Actions, 'First child receives all triggers after being spawned');
        $this->assertEquals(1, $child2Actions, 'Second child spawned and receives trigger');

        // Trigger both children
        $region->trigger(new stdClass());
        $this->assertEquals(3, $child1Actions, 'Both children receive trigger');
        $this->assertEquals(2, $child2Actions);
    }
}
