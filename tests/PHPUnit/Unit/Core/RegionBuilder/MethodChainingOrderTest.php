<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\BuildStep;
use Noem\State\Feature\ExtendedState\ContextMetaType;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Builder methods can be chained in any logical order
 */
#[Group('region-builder')]
#[Group('fluent-api')]
class MethodChainingOrderTest extends TestCase
{
    public function testMethodsCanBeChainedInVariousOrders(): void
    {
        // Order 1: states, initial, final, features
        $builder1 = new RegionBuilder();
        $builder1->setStates('a', 'b', 'c')
                 ->markInitial('a')
                 ->markFinal('c')
                 ->enableFeatures();
        
        $region1 = $builder1->build();
        $this->assertInstanceOf(Region::class, $region1);
        
        // Order 2: initial, states, features, final
        $builder2 = new RegionBuilder();
        $builder2->markInitial('a')
                 ->setStates('a', 'b', 'c')
                 ->enableFeatures()
                 ->markFinal('c');
        
        $region2 = $builder2->build();
        $this->assertInstanceOf(Region::class, $region2);
        
        // Order 3: features, final, states, initial
        $builder3 = new RegionBuilder();
        $builder3->enableFeatures()
                 ->markFinal('c')
                 ->setStates('a', 'b', 'c')
                 ->markInitial('a');
        
        $region3 = $builder3->build();
        $this->assertInstanceOf(Region::class, $region3);
    }
    
    public function testEventHandlersCanBeRegisteredInAnyOrder(): void
    {
        $builder = new RegionBuilder();
        $builder->onAction('idle', fn(object $t) => null)
                ->setStates('idle', 'processing')
                ->onEnter('processing', fn(object $t) => null)
                ->onExit('idle', fn(object $t) => null);
        
        $region = $builder->build();
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testBuildStepsCanBeAddedInAnyOrder(): void
    {
        $builder = new RegionBuilder();
        
        $step = new class implements BuildStep {
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                return $next($builder);
            }
        };
        
        $builder->addBuildStep($step)
                ->setStates('idle')
                ->addBuildStep($step)
                ->markInitial('idle');
        
        $region = $builder->build();
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testMetadataCanBeSetAtAnyPoint(): void
    {
        $builder = new RegionBuilder();
        
        $builder->setMetaData(['key1' => 'value1'], ContextMetaType::get())
                ->setStates('idle', 'processing')
                ->setMetaData(['key2' => 'value2'], ContextMetaType::get())
                ->markInitial('idle')
                ->setMetaData(['key3' => 'value3'], ContextMetaType::get());
        
        $region = $builder->build();
        $this->assertInstanceOf(Region::class, $region);
    }
    
    public function testComplexChainWithMixedMethods(): void
    {
        $builder = new RegionBuilder();
        
        $step = new class implements BuildStep {
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                return $next($builder);
            }
        };
        
        $childRegion = (new RegionBuilder())->setStates('child')->build();
        
        $builder->setStates('parent')
                ->markInitial('parent')
                ->addBuildStep($step)
                ->onAction('parent', fn(object $t) => null)
                ->setMetaData(['data' => 'test'], ContextMetaType::get())
                ->onEnter('parent', fn(object $t) => null)
                ->connect($childRegion)
                ->markFinal('parent')
                ->onExit('parent', fn(object $t) => null);
        
        $region = $builder->build();
        $this->assertInstanceOf(Region::class, $region);
    }
}
