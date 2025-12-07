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
 * Acceptance Criterion: RegionLoader works with nested hierarchical regions
 */
#[Group('loader')]
#[Group('integration')]
class NestedRegionsTest extends TestCase
{
    public function testNestedRegionsFromArray(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        $parentActions = 0;
        $childActions = 0;

        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'regions' => [
                        [
                            'states' => [
                                [
                                    'name' => 'child1',
                                    'transitions' => [
                                        [
                                            'target' => 'child2',
                                            'guard' => fn(object $t): bool => $t->moveChild ?? false,
                                        ],
                                    ],
                                    'action' => [
                                        ['run' => function (object $t) use (&$childActions) {
                                            $childActions++;
                                            return 'child1';
                                        }],
                                    ],
                                ],
                                [
                                    'name' => 'child2',
                                    'action' => [
                                        ['run' => function (object $t) use (&$childActions) {
                                            $childActions++;
                                            return 'child2';
                                        }],
                                    ],
                                ],
                            ],
                            'initial' => 'child1',
                        ],
                    ],
                    'action' => [
                        ['run' => function (object $t) use (&$parentActions) {
                            $parentActions++;
                            return 'parent';
                        }],
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

        // Trigger action - should reach both parent and child (child fires first due to DispatchAction order)
        $region->trigger(new stdClass());
        $this->assertEquals(1, $childActions, 'Child action fires first');
        $this->assertEquals(1, $parentActions, 'Parent action fires after child');

        // Transition child region
        $trigger = new stdClass();
        $trigger->moveChild = true;
        $region->trigger($trigger);

        // Child action fires before transition, then parent action
        $this->assertEquals(2, $childActions, 'Child action fires before transitioning');
        $this->assertEquals(2, $parentActions, 'Parent action also fires');

        // Trigger again - now child2 actions should fire
        $region->trigger(new stdClass());
        $this->assertEquals(3, $childActions, 'Child2 action fires');
        $this->assertEquals(3, $parentActions);
    }

    public function testNestedRegionsFromYaml(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        $GLOBALS['parent_log'] = 0;
        $GLOBALS['child_log'] = 0;

        $yaml = <<<'YAML'
        states:
          - name: parent
            regions:
              - states:
                  - name: child
                    action:
                      - run: !php "function(object $t) { $GLOBALS['child_log']++; return 'child'; }"
                initial: child
            action:
              - run: !php "function(object $t) { $GLOBALS['parent_log']++; return 'parent'; }"
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

        // Trigger - both regions should receive action
        $region->trigger(new stdClass());
        $this->assertEquals(1, $GLOBALS['child_log'], 'Child receives action');
        $this->assertEquals(1, $GLOBALS['parent_log'], 'Parent receives action');

        // Trigger again
        $region->trigger(new stdClass());
        $this->assertEquals(2, $GLOBALS['child_log']);
        $this->assertEquals(2, $GLOBALS['parent_log']);

        // Cleanup
        unset($GLOBALS['parent_log'], $GLOBALS['child_log']);
    }

    public function testMultipleNestedRegions(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader(), new TransitionsFeature());

        $child1Actions = 0;
        $child2Actions = 0;

        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'regions' => [
                        [
                            'states' => [
                                [
                                    'name' => 'child1',
                                    'action' => [
                                        ['run' => function (object $t) use (&$child1Actions) {
                                            $child1Actions++;
                                            return 'child1';
                                        }],
                                    ],
                                ],
                            ],
                            'initial' => 'child1',
                        ],
                        [
                            'states' => [
                                [
                                    'name' => 'child2',
                                    'action' => [
                                        ['run' => function (object $t) use (&$child2Actions) {
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
            'initial' => 'parent',
        ];

        $region = $builder->build([
            'loader' => [
                'array' => $config,
            ],
        ]);

        // Both child regions should receive actions
        $region->trigger(new stdClass());
        $this->assertEquals(1, $child1Actions, 'First child receives action');
        $this->assertEquals(1, $child2Actions, 'Second child receives action');

        $region->trigger(new stdClass());
        $this->assertEquals(2, $child1Actions);
        $this->assertEquals(2, $child2Actions);
    }
}
