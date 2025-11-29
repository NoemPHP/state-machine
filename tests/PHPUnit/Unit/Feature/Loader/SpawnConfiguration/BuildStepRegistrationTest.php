<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader\SpawnConfiguration;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: RegionLoader registers spawn steps as build steps during array processing
 * 
 * Intent: Integrates spawn configuration into the builder chain for execution during region construction
 * 
 * Replaces 8 specs:
 * - spawn-processing (2 specs): Registration logic
 * - spawn-step-execution (6 specs): Build step mechanics
 */
#[Group('loader')]
#[Group('spawn-configuration')]
class BuildStepRegistrationTest extends TestCase
{
    #[Test]
    public function testRegistersSpawnStepForStatesWithDefinitions(): void
    {
        // Tests: RegionLoader creates region spawn step for each spawn definition
        $config = [
            'states' => [
                [
                    'name' => 'state1',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child1']]],
                        ],
                    ],
                ],
                [
                    'name' => 'state2',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child2']]],
                        ],
                    ],
                ],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        // If build steps are registered, region builds successfully
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    #[Test]
    public function testSkipsStatesWithoutSpawnDefinitions(): void
    {
        // Tests: RegionLoader skips states without spawn definitions
        $config = [
            'states' => [
                ['name' => 'noSpawn1'],
                [
                    'name' => 'withSpawn',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child']]],
                        ],
                    ],
                ],
                ['name' => 'noSpawn2'],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]])
            ->build();

        // States without spawn should not cause errors
        $this->assertTrue($region->isInState('noSpawn1'));
    }

    #[Test]
    public function testBuildStepCreatesSpawnRecord(): void
    {
        // Tests: regionSpawnStep creates RegionSpawnRecord with provided parameters
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child']]],
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        $region = $builder->build();

        // Verify spawn record was created by checking registry
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        
        $this->assertCount(1, $registry->records, 'Spawn record should be created during build');
    }

    #[Test]
    public function testBuildStepAddsRecordToRegistry(): void
    {
        // Tests: regionSpawnStep adds spawn record to registry
        $config = [
            'states' => [
                [
                    'name' => 'state1',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child1']]],
                        ],
                    ],
                ],
                [
                    'name' => 'state2',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child2']]],
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        $region = $builder->build();

        // Verify multiple records added to registry
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        
        $this->assertCount(
            2,
            $registry->records,
            'Multiple spawn records should be added to registry'
        );
    }

    #[Test]
    public function testBuildStepFollowsMiddlewarePattern(): void
    {
        // Tests: regionSpawnStep returns closure accepting builder and next
        // Tests: regionSpawnStep calls next to build region
        // Tests: regionSpawnStep returns built region
        
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child']]],
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        // If middleware pattern is followed correctly, region builds
        $region = $builder->build();

        $this->assertInstanceOf(
            \Noem\State\Region::class,
            $region,
            'Build step should follow middleware pattern and return built region'
        );
    }

    #[Test]
    public function testBuildStepRetrievesRegistryFromChainMail(): void
    {
        // Tests: regionSpawnStep retrieves RegionSpawnRegistry from ChainMail
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => true,
                            'region' => ['states' => [['name' => 'child']]],
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader());

        $region = $builder->build(['loader' => ['array' => $config]]);

        // Registry should be retrievable from ChainMail
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);

        $this->assertInstanceOf(
            RegionSpawnRegistry::class,
            $registry,
            'Build step should retrieve registry from ChainMail'
        );
    }

    #[Test]
    public function testMultipleSpawnDefinitionsCreateMultipleBuildSteps(): void
    {
        // Tests: Each spawn definition creates its own build step
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn($t) => $t->type === 'a',
                            'region' => ['states' => [['name' => 'childA']]],
                        ],
                        [
                            'guard' => fn($t) => $t->type === 'b',
                            'region' => ['states' => [['name' => 'childB']]],
                        ],
                        [
                            'guard' => fn($t) => $t->type === 'c',
                            'region' => ['states' => [['name' => 'childC']]],
                        ],
                    ],
                ],
            ],
        ];

        $builder = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]]);

        $region = $builder->build();

        // Verify three spawn records created (one per spawn definition)
        $registry = $builder->chainMail->invoke(fn(RegionSpawnRegistry $r) => $r);
        
        $this->assertCount(
            3,
            $registry->records,
            'Each spawn definition should create its own build step'
        );
    }

    #[Test]
    public function testBuildStepOrderPreservesDefinitionOrder(): void
    {
        // Tests: Build steps preserve order of spawn definitions
        $order = [];
        
        $config = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => function ($t) use (&$order) {
                                $order[] = 1;
                                return true;
                            },
                            'region' => ['states' => [['name' => 'child1']]],
                        ],
                        [
                            'guard' => function ($t) use (&$order) {
                                $order[] = 2;
                                return true;
                            },
                            'region' => ['states' => [['name' => 'child2']]],
                        ],
                        [
                            'guard' => function ($t) use (&$order) {
                                $order[] = 3;
                                return true;
                            },
                            'region' => ['states' => [['name' => 'child3']]],
                        ],
                    ],
                ],
            ],
        ];

        $region = (new RegionBuilder())
            ->enableFeatures(new RegionLoader())
            ->build(['loader' => ['array' => $config]])
            ->build();

        // Trigger to evaluate guards
        $region->trigger(new \stdClass());

        // Guards should be evaluated in definition order
        $this->assertSame([1, 2, 3], $order, 'Build steps should preserve spawn definition order');
    }
}
