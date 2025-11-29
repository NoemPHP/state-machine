<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Loader;

use Noem\State\Feature\Loader\LoaderChains\Schema;
use Noem\State\Feature\Loader\LoaderChains\TransformArray;
use Noem\State\Feature\Loader\ProcessArray;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ProcessArray extracts nested regions from configuration
 */
#[Group('loader')]
#[Group('array-processing')]
class NestedRegionExtractionTest extends TestCase
{
    public function testExtractsNestedRegionsFromConfiguration(): void
    {
        $processor = new ProcessArray(new Schema(), new TransformArray());
        
        // Test the extractConfig method directly to verify regions extraction
        $reflection = new \ReflectionClass($processor);
        $method = $reflection->getMethod('extractConfig');
        $method->setAccessible(true);
        
        $statesConfig = [
            [
                'name' => 'parent',
                'regions' => [
                    [
                        'states' => [
                            ['name' => 'child1'],
                        ],
                    ],
                ],
            ],
        ];
        
        [$states, $regions, $transitions, $callbacks] = $method->invoke($processor, $statesConfig);
        
        $this->assertContains('parent', $states);
        $this->assertArrayHasKey('parent', $regions);
        $this->assertCount(1, $regions['parent']);
    }
}
