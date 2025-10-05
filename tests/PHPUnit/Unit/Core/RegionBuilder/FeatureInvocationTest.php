<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Features are invoked with the builder's ChainMail instance
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class FeatureInvocationTest extends TestCase
{
    public function testFeatureReceivesCorrectChainMail(): void
    {
        // Create custom ChainMail with required services
        $customChainMail = new ChainMail();
        $customChainMail->supply(
            fn(): \Noem\State\Chains\ConnectedRegions => new \Noem\State\Chains\ConnectedRegions(),
            fn(\Noem\State\Chains\ConnectedRegions $connections): \Noem\State\Chains\Meta => new \Noem\State\Chains\Meta($connections)
        );

        $builder = new RegionBuilder($customChainMail);
        
        $receivedChainMail = null;
        
        $feature = new class($receivedChainMail) implements Feature {
            public function __construct(private mixed &$receivedChainMail) {}
            
            public function __invoke(ChainMail $chainMail): void
            {
                $this->receivedChainMail = $chainMail;
            }
        };
        
        $builder->enableFeatures($feature);
        
        $this->assertSame(
            $customChainMail,
            $receivedChainMail,
            'Feature should receive the exact ChainMail instance passed to builder'
        );
    }
    
    public function testFeatureInvocationOrder(): void
    {
        $builder = new RegionBuilder();
        $order = [];
        
        $createFeature = function(string $id) use (&$order): Feature {
            return new class($id, $order) implements Feature {
                public function __construct(
                    private string $id,
                    private array &$order
                ) {}
                
                public function __invoke(ChainMail $chainMail): void
                {
                    $this->order[] = $this->id;
                }
            };
        };
        
        $builder->enableFeatures(
            $createFeature('A'),
            $createFeature('B'),
            $createFeature('C')
        );
        
        $this->assertSame(
            ['A', 'B', 'C'],
            $order,
            'Features should be invoked in the order they are registered'
        );
    }
}
