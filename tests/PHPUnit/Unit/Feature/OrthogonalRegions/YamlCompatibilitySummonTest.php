<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\Feature\RegionLoader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: summon() works with RegionBuilder created from RegionLoader
 */
#[Group('orthogonal-regions')]
#[Group('yaml-compatibility')]
class YamlCompatibilitySummonTest extends TestCase
{
    public function testSummonAcceptsRegionBuilderFromLoader(): void
    {
        $childConfig = [
            'states' => ['child'],
            'initial' => 'child',
        ];

        $childBuilder = (new RegionLoader(new RegionBuilder()))->fromArray($childConfig);

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $runtime = $this->summon($childBuilder);
                $this->assertNotNull($runtime);
            })
            ->build();

        $region->init();

        $this->assertTrue(true); // If we got here, test passed
    }

    public function testSummonExecutesRegionFromArray(): void
    {
        $childExecuted = false;

        $childConfig = [
            'states' => ['child', 'done'],
            'initial' => 'child',
            'final' => ['done'],
            'on' => [
                'child' => [
                    'enter' => function (object $t) use (&$childExecuted) {
                        $childExecuted = true;
                    },
                ],
            ],
        ];

        $childBuilder = (new RegionLoader(new RegionBuilder()))->fromArray($childConfig);

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $runtime = $this->summon($childBuilder);
                $runtime->run();
            })
            ->build();

        $region->init();

        $this->assertTrue($childExecuted);
    }

    public function testSummonMultipleRegionsFromArrays(): void
    {
        $child1Executed = false;
        $child2Executed = false;

        $config1 = [
            'states' => ['c1'],
            'initial' => 'c1',
            'on' => [
                'c1' => [
                    'enter' => function (object $t) use (&$child1Executed) {
                        $child1Executed = true;
                    },
                ],
            ],
        ];

        $config2 = [
            'states' => ['c2'],
            'initial' => 'c2',
            'on' => [
                'c2' => [
                    'enter' => function (object $t) use (&$child2Executed) {
                        $child2Executed = true;
                    },
                ],
            ],
        ];

        $loader = new RegionLoader(new RegionBuilder());
        $builder1 = $loader->fromArray($config1);
        $builder2 = $loader->fromArray($config2);

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($builder1, $builder2) {
                $this->summon($builder1)->run();
                $this->summon($builder2)->run();
            })
            ->build();

        $region->init();

        $this->assertTrue($child1Executed);
        $this->assertTrue($child2Executed);
    }

    public function testDynamicallySummonRegionsBasedOnArrayData(): void
    {
        $executionLog = [];

        $createChildConfig = function ($name) {
            return [
                'states' => [$name],
                'initial' => $name,
            ];
        };

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($createChildConfig, &$executionLog) {
                $children = ['alpha', 'beta', 'gamma'];

                foreach ($children as $child) {
                    $config = $createChildConfig($child);
                    $builder = (new RegionLoader(new RegionBuilder()))->fromArray($config);
                    $runtime = $this->summon($builder);
                    $runtime->run();
                    $executionLog[] = $child;
                }
            })
            ->build();

        $region->init();

        $this->assertEquals(['alpha', 'beta', 'gamma'], $executionLog);
    }

    public function testSummonCanLoadComplexYamlLikeStructure(): void
    {
        $childStates = [];

        $complexConfig = [
            'states' => ['idle', 'processing', 'validating', 'done'],
            'initial' => 'idle',
            'final' => ['done'],
            'on' => [
                'idle' => [
                    'enter' => function (object $t) use (&$childStates) {
                        $childStates[] = 'idle';
                    },
                    'action' => function (object $t) {
                        return 'processing';
                    },
                ],
                'processing' => [
                    'enter' => function (object $t) use (&$childStates) {
                        $childStates[] = 'processing';
                    },
                    'action' => function (object $t) {
                        return 'validating';
                    },
                ],
                'validating' => [
                    'enter' => function (object $t) use (&$childStates) {
                        $childStates[] = 'validating';
                    },
                    'action' => function (object $t) {
                        return 'done';
                    },
                ],
                'done' => [
                    'enter' => function (object $t) use (&$childStates) {
                        $childStates[] = 'done';
                    },
                ],
            ],
        ];

        $childBuilder = (new RegionLoader(new RegionBuilder()))->fromArray($complexConfig);

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $runtime = $this->summon($childBuilder);
                $runtime->run();
            })
            ->build();

        $region->init();

        $this->assertEquals(['idle', 'processing', 'validating', 'done'], $childStates);
    }
}
