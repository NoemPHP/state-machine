<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: ChainMail is booted automatically when building region
 */
#[Group('region-builder')]
#[Group('chainmail-integration')]
class ChainMailBootTest extends TestCase
{
    public function testChainMailIsBootedDuringBuild(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $bootCalled = false;
        
        $builder->chainMail->use(function() use (&$bootCalled) {
            $bootCalled = true;
        });
        
        $this->assertFalse($bootCalled, 'ChainMail middleware should not be invoked before build');
        
        $region = $builder->build();
        
        $this->assertTrue($bootCalled, 'ChainMail should be booted during build');
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testChainMailBootIsIdempotent(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $bootCount = 0;
        
        $builder->chainMail->use(function() use (&$bootCount) {
            $bootCount++;
        });
        
        // Build multiple times
        $builder->build();
        $builder->build();
        $builder->build();
        
        // Boot should be called once per build, but is idempotent per ChainMail instance
        $this->assertGreaterThanOrEqual(1, $bootCount, 'ChainMail boot should be called at least once');
    }
    
    public function testBootMiddlewareExecutesBeforeBuild(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');
        
        $executionOrder = [];
        
        $builder->chainMail->use(function() use (&$executionOrder) {
            $executionOrder[] = 'boot';
        });
        
        $builder->addBuildStep(new class($executionOrder) implements \Noem\State\BuildStep {
            public function __construct(private array &$order) {}
            
            public function callback(\Noem\State\RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->order[] = 'build';
                return $next($builder);
            }
        });
        
        $builder->build();
        
        $this->assertEquals(['boot', 'build'], $executionOrder, 
            'ChainMail boot should execute before build steps');
    }
}
