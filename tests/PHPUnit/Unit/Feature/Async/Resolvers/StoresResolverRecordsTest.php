<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Resolvers;

use Noem\State\Feature\Async\ResolverRecord;
use Noem\State\Feature\Async\Resolvers;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Resolvers stores resolver records per region
 */
#[Group('async'), Group('resolver-registry')]
class StoresResolverRecordsTest extends TestCase
{
    public function testStoresResolverRecords(): void
    {
        $resolvers = new Resolvers();
        
        $region = (new RegionBuilder())->setStates('idle')->build();
        $callback = fn() => 'computed';
        
        $record = new ResolverRecord($region, 'myProperty', $callback);
        $resolvers->addResolver($record);
        
        $this->assertTrue($resolvers->hasResolver($region, 'myProperty'));
    }
    
    public function testStoresMultipleResolversForSameRegion(): void
    {
        $resolvers = new Resolvers();
        
        $region = (new RegionBuilder())->setStates('idle')->build();
        
        $record1 = new ResolverRecord($region, 'property1', fn() => 'value1');
        $record2 = new ResolverRecord($region, 'property2', fn() => 'value2');
        
        $resolvers->addResolver($record1);
        $resolvers->addResolver($record2);
        
        $this->assertTrue($resolvers->hasResolver($region, 'property1'));
        $this->assertTrue($resolvers->hasResolver($region, 'property2'));
    }
    
    public function testStoresResolversPerRegion(): void
    {
        $resolvers = new Resolvers();
        
        $region1 = (new RegionBuilder())->setStates('idle')->build();
        $region2 = (new RegionBuilder())->setStates('active')->build();
        
        $record1 = new ResolverRecord($region1, 'property', fn() => 'region1-value');
        $record2 = new ResolverRecord($region2, 'property', fn() => 'region2-value');
        
        $resolvers->addResolver($record1);
        $resolvers->addResolver($record2);
        
        $this->assertTrue($resolvers->hasResolver($region1, 'property'));
        $this->assertTrue($resolvers->hasResolver($region2, 'property'));
        
        $retrieved1 = $resolvers->getResolver($region1, 'property');
        $retrieved2 = $resolvers->getResolver($region2, 'property');
        
        $this->assertNotSame($retrieved1, $retrieved2, 'Different regions should have separate resolver records');
    }
    
    public function testOverwritesExistingResolverForSameKey(): void
    {
        $resolvers = new Resolvers();
        
        $region = (new RegionBuilder())->setStates('idle')->build();
        
        $callback1 = fn() => 'first';
        $callback2 = fn() => 'second';
        
        $record1 = new ResolverRecord($region, 'property', $callback1);
        $record2 = new ResolverRecord($region, 'property', $callback2);
        
        $resolvers->addResolver($record1);
        $resolvers->addResolver($record2);
        
        $retrieved = $resolvers->getResolver($region, 'property');
        $this->assertSame($callback2, $retrieved->resolver, 'Later resolver should overwrite earlier one');
    }
}
