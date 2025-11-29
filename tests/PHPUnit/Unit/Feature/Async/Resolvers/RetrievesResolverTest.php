<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Resolvers;

use Noem\State\Feature\Async\ResolverRecord;
use Noem\State\Feature\Async\Resolvers;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Resolvers retrieves specific resolver by region and key
 */
#[Group('async'), Group('resolver-registry')]
class RetrievesResolverTest extends TestCase
{
    public function testRetrievesResolverByRegionAndKey(): void
    {
        $resolvers = new Resolvers();
        
        $region = (new RegionBuilder())->setStates('idle')->build();
        $callback = fn() => 'computed-value';
        
        $record = new ResolverRecord($region, 'myProperty', $callback);
        $resolvers->addResolver($record);
        
        $retrieved = $resolvers->getResolver($region, 'myProperty');
        
        $this->assertInstanceOf(ResolverRecord::class, $retrieved);
        $this->assertSame($region, $retrieved->region);
        $this->assertSame('myProperty', $retrieved->key);
        $this->assertSame($callback, $retrieved->resolver);
    }
    
    public function testRetrievesCorrectResolverWhenMultipleExist(): void
    {
        $resolvers = new Resolvers();
        
        $region = (new RegionBuilder())->setStates('idle')->build();
        
        $callback1 = fn() => 'value1';
        $callback2 = fn() => 'value2';
        $callback3 = fn() => 'value3';
        
        $resolvers->addResolver(new ResolverRecord($region, 'prop1', $callback1));
        $resolvers->addResolver(new ResolverRecord($region, 'prop2', $callback2));
        $resolvers->addResolver(new ResolverRecord($region, 'prop3', $callback3));
        
        $retrieved = $resolvers->getResolver($region, 'prop2');
        
        $this->assertSame('prop2', $retrieved->key);
        $this->assertSame($callback2, $retrieved->resolver);
    }
    
    public function testReturnsNullForNonExistentResolver(): void
    {
        $resolvers = new Resolvers();
        
        $region = (new RegionBuilder())->setStates('idle')->build();
        
        $retrieved = $resolvers->getResolver($region, 'nonexistent');
        
        $this->assertNull($retrieved);
    }
    
    public function testRetrievesResolverForCorrectRegionOnly(): void
    {
        $resolvers = new Resolvers();
        
        $region1 = (new RegionBuilder())->setStates('idle')->build();
        $region2 = (new RegionBuilder())->setStates('active')->build();
        
        $callback1 = fn() => 'region1-value';
        $callback2 = fn() => 'region2-value';
        
        $resolvers->addResolver(new ResolverRecord($region1, 'property', $callback1));
        $resolvers->addResolver(new ResolverRecord($region2, 'property', $callback2));
        
        $retrieved1 = $resolvers->getResolver($region1, 'property');
        $retrieved2 = $resolvers->getResolver($region2, 'property');
        
        $this->assertSame($callback1, $retrieved1->resolver);
        $this->assertSame($callback2, $retrieved2->resolver);
        
        // Wrong region should not find the resolver
        $wrongRegion = (new RegionBuilder())->setStates('other')->build();
        $this->assertNull($resolvers->getResolver($wrongRegion, 'property'));
    }
}
