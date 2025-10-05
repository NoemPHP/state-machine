<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Middleware\Mesh;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: build accepts optional Mesh or iterable for feature arguments
 */
#[Group('region-builder')]
#[Group('builder-lifecycle')]
class BuildWithFeatureArgsTest extends TestCase
{
    public function testBuildAcceptsNullFeatureArgs(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $region = $builder->build(null);
        
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testBuildAcceptsMeshFeatureArgs(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $data = ['arg1' => 'value1', 'arg2' => 'value2'];
        $featureArgs = new Mesh($data);
        
        $region = $builder->build($featureArgs);
        
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testBuildAcceptsIterableFeatureArgs(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $featureArgs = ['config1' => 'value1', 'config2' => 'value2'];
        
        $region = $builder->build($featureArgs);
        
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testBuildAcceptsArrayIteratorFeatureArgs(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $featureArgs = new \ArrayIterator(['key' => 'value']);
        
        $region = $builder->build($featureArgs);
        
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testFeatureArgsArePassedToEnhanceChain(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $receivedParams = null;
        
        $builder->chainMail->use(function(\Noem\State\Chains\EnhanceRegionBuilder $enhance) use (&$receivedParams) {
            $enhance->link(function(\Noem\State\Chains\Params\BuildParams $params, callable $next) use (&$receivedParams) {
                $receivedParams = $params;
                return $next($params);
            });
        });
        
        $data = ['test' => 'data'];
        $featureArgs = new Mesh($data);
        $region = $builder->build($featureArgs);
        
        // BuildParams extends Mesh, so we can access feature args via array access
        $this->assertInstanceOf(\Noem\State\Chains\Params\BuildParams::class, $receivedParams);
        $this->assertEquals('data', $receivedParams['test']);
        $this->assertInstanceOf(Region::class, $region);
    }
}
