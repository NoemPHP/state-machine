<?php

declare(strict_types=1);

namespace Tests\PHPUnit\Unit\Feature\Loader\Holon;

use Noem\State\Feature\Loader\Holon;
use Noem\State\Feature\Loader\RegionLoader;
use Noem\State\Region;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: quickRegion accepts features array
 */
#[Group('loader'), Group('holon'), Group('holon-convenience')]
class QuickRegionFeaturesTest extends TestCase
{
    public function testAcceptsFeaturesArray(): void
    {
        // Arrange
        $states = [
            ['name' => 'start', 'initial' => true, 'final' => true],
        ];
        
        $features = [
            RegionLoader::class
        ];
        
        // Act
        $region = Holon::quickRegion($states, $features);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testWorksWithEmptyFeaturesArray(): void
    {
        // Arrange
        $states = [
            ['name' => 'active', 'initial' => true, 'final' => true],
        ];
        
        // Act
        $region = Holon::quickRegion($states, []);
        
        // Assert
        $this->assertInstanceOf(Region::class, $region);
        $this->assertEquals('active', $region->currentState());
    }
    
    public function testDefaultsToNoAdditionalFeatures(): void
    {
        // Arrange
        $states = [
            ['name' => 'simple', 'initial' => true, 'final' => true],
        ];
        
        // Act - no features parameter
        $region = Holon::quickRegion($states);
        
        // Assert - should work with just RegionLoader (auto-added)
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testFeaturesAppliedToRegion(): void
    {
        // Arrange
        $states = [
            [
                'name' => 'start',
                'initial' => true,
                'transitions' => [
                    ['target' => 'end']
                ]
            ],
            [
                'name' => 'end',
                'final' => true
            ],
        ];
        
        // Include RegionLoader explicitly
        $features = [
            RegionLoader::class
        ];
        
        // Act
        $region = Holon::quickRegion($states, $features);
        
        // Assert - transitions should work (proving features applied)
        $this->assertEquals('start', $region->currentState());
        $region->trigger(new \stdClass());
        $this->assertEquals('end', $region->currentState());
    }
}
