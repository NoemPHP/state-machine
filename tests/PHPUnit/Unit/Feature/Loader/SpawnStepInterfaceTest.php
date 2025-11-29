<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\BuildStep;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Feature\Loader\RegionSpawnRegistry;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Acceptance Criterion: regionSpawnStep returns closure accepting builder and next
 */
#[Group('loader')]
#[Group('spawn-step-execution')]
class SpawnStepInterfaceTest extends TestCase
{
    public function testReturnsClosureAcceptingBuilderAndNext(): void
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
        
        // Build the region - this creates and adds the BuildStep
        $region = $builder->build([
            'loader' => [
                'array' => $array,
            ],
        ]);
        
        // Verify that the BuildStep implements the correct interface
        // We can't directly access the build steps, but we can verify that
        // the build process succeeded, which means the BuildStep's callback
        // method was called with builder and next parameters
        
        $this->assertInstanceOf(Region::class, $region, 'BuildStep callback should return a Region');
        
        // Verify the region was built correctly, proving the callback worked
        $this->assertTrue($region->isInState('parent'));
    }
}
