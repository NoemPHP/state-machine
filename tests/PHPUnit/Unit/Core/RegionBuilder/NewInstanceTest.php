<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: newInstance creates a new builder sharing the same ChainMail configuration
 */
#[Group('region-builder')]
#[Group('builder-lifecycle')]
class NewInstanceTest extends TestCase
{
    public function testNewInstanceCreatesNewBuilder(): void
    {
        $original = new RegionBuilder();
        $original->setStates('idle', 'processing');
        
        $newInstance = $original->newInstance();
        
        $this->assertInstanceOf(RegionBuilder::class, $newInstance);
        $this->assertNotSame($original, $newInstance, 'newInstance should create a new builder instance');
    }
    
    public function testNewInstanceSharesChainMail(): void
    {
        $original = new RegionBuilder();
        
        $newInstance = $original->newInstance();
        
        $this->assertSame(
            $original->chainMail,
            $newInstance->chainMail,
            'newInstance should share the same ChainMail instance'
        );
    }
    
    public function testNewInstanceDoesNotCopyConfiguration(): void
    {
        $original = new RegionBuilder();
        $original->setStates('idle', 'processing');
        
        $newInstance = $original->newInstance();
        $newInstance->setStates('fresh', 'clean');
        
        // Both should be able to build independently
        $originalRegion = $original->build();
        $newRegion = $newInstance->build();
        
        $this->assertTrue($originalRegion->isInState('idle'));
        $this->assertTrue($newRegion->isInState('fresh'));
    }
    
    public function testNewInstanceSharesRegisteredServices(): void
    {
        $original = new RegionBuilder();
        
        $newInstance = $original->newInstance();
        
        // Both instances should share the same ChainMail
        $this->assertSame($original->chainMail, $newInstance->chainMail);
        
        // Both should be able to access the same services through shared ChainMail
        // Build both to verify they work properly
        $original->setStates('state1');
        $newInstance->setStates('state2');
        
        $region1 = $original->build();
        $region2 = $newInstance->build();
        
        $this->assertInstanceOf(\Noem\State\Region::class, $region1);
        $this->assertInstanceOf(\Noem\State\Region::class, $region2);
    }
    
    public function testNewInstanceCanBuildIndependentRegions(): void
    {
        $original = new RegionBuilder();
        $original->setStates('state1');
        
        $newInstance = $original->newInstance();
        $newInstance->setStates('state2');
        
        $region1 = $original->build();
        $region2 = $newInstance->build();
        
        $this->assertInstanceOf(\Noem\State\Region::class, $region1);
        $this->assertInstanceOf(\Noem\State\Region::class, $region2);
        $this->assertNotSame($region1, $region2);
    }
}
