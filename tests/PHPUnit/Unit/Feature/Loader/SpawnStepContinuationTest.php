<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: regionSpawnStep calls next to build region
 */
#[Group('loader')]
#[Group('spawn-step-execution')]
class SpawnStepContinuationTest extends TestCase
{
    public function testCallsNextToBuildRegion(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new RegionLoader());
        
        $array = [
            'states' => [
                [
                    'name' => 'parent',
                    'spawn' => [
                        [
                            'guard' => fn(object $t): bool => true,
                            'region' => [
                                'states' => [
                                    ['name' => 'child'],
                                ],
                                'initial' => 'child',
                            ],
                        ],
                    ],
                ],
            ],
            'initial' => 'parent',
        ];
        
        // Build the region - the BuildStep must call next() to complete the build chain
        $region = $builder->build([
            'loader' => [
                'array' => $array,
            ],
        ]);
        
        // If next() wasn't called, the region wouldn't be properly built
        $this->assertNotNull($region, 'Region should be built, proving next() was called');
        $this->assertTrue($region->isInState('parent'), 'Region should be in initial state');
    }
}
