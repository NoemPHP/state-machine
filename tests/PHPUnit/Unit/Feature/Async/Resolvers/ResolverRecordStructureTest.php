<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Feature\Async\Resolvers;

use Noem\State\Feature\Async\ResolverRecord;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ResolverRecord stores region, key, and resolver callback
 */
#[Group('async'), Group('resolver-registry')]
class ResolverRecordStructureTest extends TestCase
{
    public function testStoresRegionKeyAndResolver(): void
    {
        $region = (new RegionBuilder())->setStates('idle')->build();
        $key = 'myProperty';
        $resolver = fn() => 'computed-value';
        
        $record = new ResolverRecord($region, $key, $resolver);
        
        $this->assertSame($region, $record->region);
        $this->assertSame($key, $record->key);
        $this->assertSame($resolver, $record->resolver);
    }
    
    public function testPropertiesAreReadonly(): void
    {
        $region = (new RegionBuilder())->setStates('idle')->build();
        $resolver = fn() => 'value';
        
        $record = new ResolverRecord($region, 'property', $resolver);
        
        // Readonly properties cannot be modified
        $reflection = new \ReflectionClass($record);
        
        $regionProp = $reflection->getProperty('region');
        $this->assertTrue($regionProp->isReadOnly(), 'region property should be readonly');
        
        $keyProp = $reflection->getProperty('key');
        $this->assertTrue($keyProp->isReadOnly(), 'key property should be readonly');
        
        $resolverProp = $reflection->getProperty('resolver');
        $this->assertTrue($resolverProp->isReadOnly(), 'resolver property should be readonly');
    }
    
    public function testPropertiesArePublic(): void
    {
        $region = (new RegionBuilder())->setStates('idle')->build();
        $resolver = fn() => 'value';
        
        $record = new ResolverRecord($region, 'property', $resolver);
        
        // Public properties can be accessed directly
        $reflection = new \ReflectionClass($record);
        
        $this->assertTrue($reflection->getProperty('region')->isPublic());
        $this->assertTrue($reflection->getProperty('key')->isPublic());
        $this->assertTrue($reflection->getProperty('resolver')->isPublic());
    }
    
    public function testCreatesRecordWithDifferentRegions(): void
    {
        $region1 = (new RegionBuilder())->setStates('idle')->build();
        $region2 = (new RegionBuilder())->setStates('active')->build();
        
        $record1 = new ResolverRecord($region1, 'property', fn() => 'value1');
        $record2 = new ResolverRecord($region2, 'property', fn() => 'value2');
        
        $this->assertNotSame($record1->region, $record2->region);
        $this->assertSame('property', $record1->key);
        $this->assertSame('property', $record2->key);
    }
    
    public function testResolverIsCallable(): void
    {
        $region = (new RegionBuilder())->setStates('idle')->build();
        $expectedValue = 'computed-result';
        
        $resolver = fn() => $expectedValue;
        $record = new ResolverRecord($region, 'property', $resolver);
        
        $this->assertIsCallable($record->resolver);
        $this->assertSame($expectedValue, ($record->resolver)());
    }
    
    public function testAcceptsClosureWithParameters(): void
    {
        $region = (new RegionBuilder())->setStates('idle')->build();
        
        $resolver = fn($param1, $param2) => $param1 + $param2;
        $record = new ResolverRecord($region, 'sum', $resolver);
        
        $this->assertSame(7, ($record->resolver)(3, 4));
    }
}
