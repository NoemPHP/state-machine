<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: build accepts optional skipMiddlewares flag to bypass EnhanceRegionBuilder chain
 */
#[Group('region-builder')]
#[Group('builder-lifecycle')]
class SkipMiddlewaresTest extends TestCase
{
    public function testBuildAcceptsSkipMiddlewaresFlag(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $region = $builder->build(skipMiddlewares: true);
        
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testSkipMiddlewaresBypassesEnhanceChain(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $enhanceChainCalled = false;
        
        $builder->chainMail->use(function(\Noem\State\Chains\EnhanceRegionBuilder $enhance) use (&$enhanceChainCalled) {
            $enhance->link(function(\Noem\State\Chains\Params\BuildParams $params, callable $next) use (&$enhanceChainCalled) {
                $enhanceChainCalled = true;
                return $next($params);
            });
        });
        
        $region = $builder->build(skipMiddlewares: true);
        
        $this->assertFalse($enhanceChainCalled, 'EnhanceRegionBuilder chain should not be called when skipMiddlewares is true');
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testDefaultSkipMiddlewaresIsFalse(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $enhanceChainCalled = false;
        
        $builder->chainMail->use(function(\Noem\State\Chains\EnhanceRegionBuilder $enhance) use (&$enhanceChainCalled) {
            $enhance->link(function(\Noem\State\Chains\Params\BuildParams $params, callable $next) use (&$enhanceChainCalled) {
                $enhanceChainCalled = true;
                return $next($params);
            });
        });
        
        $region = $builder->build();
        
        $this->assertTrue($enhanceChainCalled, 'EnhanceRegionBuilder chain should be called by default');
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testSkipMiddlewaresWithFeatureArgs(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $featureArgs = ['test' => 'data'];
        
        $region = $builder->build($featureArgs, true);
        
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testSkipMiddlewaresStillExecutesBuildSteps(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $buildStepExecuted = false;
        
        $builder->addBuildStep(new class($buildStepExecuted) implements \Noem\State\BuildStep {
            public function __construct(private bool &$executed) {}
            
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->executed = true;
                return $next($builder);
            }
        });
        
        $region = $builder->build(skipMiddlewares: true);
        
        $this->assertTrue($buildStepExecuted, 'Build steps should still execute even when skipping middlewares');
        $this->assertInstanceOf(Region::class, $region);
    }
}
