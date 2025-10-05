<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can define states using setStates with variadic parameters
 */
#[Group('region-builder')]
#[Group('state-configuration')]
class SetStatesTest extends TestCase
{
    public function testSetStatesAcceptsVariadicParameters(): void
    {
        $builder = new RegionBuilder();
        
        $result = $builder->setStates('idle', 'processing', 'complete');
        
        $this->assertSame($builder, $result, 'setStates should return builder for chaining');
        
        $region = $builder->build();
        
        $this->assertTrue($region->isInState('idle'), 'Region should start in first state');
    }
    
    public function testSetStatesWithSingleState(): void
    {
        $builder = new RegionBuilder();
        
        $builder->setStates('single');
        $region = $builder->build();
        
        $this->assertTrue($region->isInState('single'));
    }
    
    public function testSetStatesWithMultipleStates(): void
    {
        $builder = new RegionBuilder();
        
        $builder->setStates('one', 'two', 'three', 'four', 'five');
        $region = $builder->build();
        
        $this->assertTrue($region->isInState('one'));
    }
}
