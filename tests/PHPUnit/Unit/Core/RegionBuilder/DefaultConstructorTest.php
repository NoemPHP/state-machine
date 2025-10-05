<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can be instantiated with default ChainMail instance
 */
#[Group('region-builder')]
#[Group('builder-lifecycle')]
class DefaultConstructorTest extends TestCase
{
    public function testBuilderCanBeInstantiatedWithoutParameters(): void
    {
        $builder = new RegionBuilder();
        
        $this->assertInstanceOf(RegionBuilder::class, $builder);
    }
    
    public function testDefaultConstructorCreatesChainMail(): void
    {
        $builder = new RegionBuilder();
        
        $this->assertInstanceOf(ChainMail::class, $builder->chainMail);
    }
    
    public function testDefaultChainMailIsConfiguredWithServices(): void
    {
        $builder = new RegionBuilder();
        
        // Verify that default services are registered
        $this->assertInstanceOf(ChainMail::class, $builder->chainMail);
        
        // ChainMail should have standard services registered
        $regionBuilder = $builder->chainMail->get(RegionBuilder::class);
        $this->assertSame($builder, $regionBuilder, 'Builder should be registered in its own ChainMail');
    }
    
    public function testBuilderCanBuildRegionWithDefaultConfiguration(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $region = $builder->build();
        
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }
}
