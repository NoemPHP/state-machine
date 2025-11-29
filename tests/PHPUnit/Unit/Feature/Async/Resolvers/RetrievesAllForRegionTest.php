<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Resolvers;

use Noem\State\Feature\Async\ResolverRecord;
use Noem\State\Feature\Async\Resolvers;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Resolvers retrieves all resolvers for a region
 */
#[Group('async'), Group('resolver-registry')]
class RetrievesAllForRegionTest extends TestCase
{
    public function testRetrievesAllResolversForRegion(): void
    {
        $resolvers = new Resolvers();
        
        $region = (new RegionBuilder())->setStates('idle')->build();
        
        $callback1 = fn() => 'value1';
        $callback2 = fn() => 'value2';
        $callback3 = fn() => 'value3';
        
        $resolvers->addResolver(new ResolverRecord($region, 'prop1', $callback1));
        $resolvers->addResolver(new ResolverRecord($region, 'prop2', $callback2));
        $resolvers->addResolver(new ResolverRecord($region, 'prop3', $callback3));
        
        $allResolvers = $resolvers->getResolversForRegion($region);
        
        $this->assertCount(3, $allResolvers);
        $this->assertArrayHasKey('prop1', $allResolvers);
        $this->assertArrayHasKey('prop2', $allResolvers);
        $this->assertArrayHasKey('prop3', $allResolvers);
    }
    
    public function testReturnsEmptyArrayForRegionWithNoResolvers(): void
    {
        $resolvers = new Resolvers();
        
        $region = (new RegionBuilder())->setStates('idle')->build();
        
        $allResolvers = $resolvers->getResolversForRegion($region);
        
        $this->assertIsArray($allResolvers);
        $this->assertEmpty($allResolvers);
    }
    
    public function testRetrievesOnlyResolversForSpecificRegion(): void
    {
        $resolvers = new Resolvers();
        
        $region1 = (new RegionBuilder())->setStates('idle')->build();
        $region2 = (new RegionBuilder())->setStates('active')->build();
        
        $resolvers->addResolver(new ResolverRecord($region1, 'region1-prop1', fn() => 'val1'));
        $resolvers->addResolver(new ResolverRecord($region1, 'region1-prop2', fn() => 'val2'));
        $resolvers->addResolver(new ResolverRecord($region2, 'region2-prop1', fn() => 'val3'));
        
        $region1Resolvers = $resolvers->getResolversForRegion($region1);
        $region2Resolvers = $resolvers->getResolversForRegion($region2);
        
        $this->assertCount(2, $region1Resolvers);
        $this->assertCount(1, $region2Resolvers);
        
        $this->assertArrayHasKey('region1-prop1', $region1Resolvers);
        $this->assertArrayHasKey('region1-prop2', $region1Resolvers);
        $this->assertArrayNotHasKey('region2-prop1', $region1Resolvers);
        
        $this->assertArrayHasKey('region2-prop1', $region2Resolvers);
        $this->assertArrayNotHasKey('region1-prop1', $region2Resolvers);
    }
    
    public function testReturnedArrayIsKeyedByResolverKey(): void
    {
        $resolvers = new Resolvers();
        
        $region = (new RegionBuilder())->setStates('idle')->build();
        
        $callback1 = fn() => 'value1';
        $callback2 = fn() => 'value2';
        
        $resolvers->addResolver(new ResolverRecord($region, 'myKey1', $callback1));
        $resolvers->addResolver(new ResolverRecord($region, 'myKey2', $callback2));
        
        $allResolvers = $resolvers->getResolversForRegion($region);
        
        $this->assertSame($callback1, $allResolvers['myKey1']->resolver);
        $this->assertSame($callback2, $allResolvers['myKey2']->resolver);
    }
    
    public function testRetrievesAllResolversAsResolverRecords(): void
    {
        $resolvers = new Resolvers();
        
        $region = (new RegionBuilder())->setStates('idle')->build();
        
        $resolvers->addResolver(new ResolverRecord($region, 'prop1', fn() => 'val1'));
        $resolvers->addResolver(new ResolverRecord($region, 'prop2', fn() => 'val2'));
        
        $allResolvers = $resolvers->getResolversForRegion($region);
        
        foreach ($allResolvers as $record) {
            $this->assertInstanceOf(ResolverRecord::class, $record);
            $this->assertSame($region, $record->region);
        }
    }
}
