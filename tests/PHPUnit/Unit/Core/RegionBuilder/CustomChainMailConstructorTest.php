<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\ExtendedState;
use Noem\State\Events;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can be instantiated with custom ChainMail instance
 */
#[Group('region-builder')]
#[Group('builder-lifecycle')]
class CustomChainMailConstructorTest extends TestCase
{
    private function createConfiguredChainMail(): ChainMail
    {
        $chainMail = new ChainMail();
        // Register required services that RegionBuilder expects
        $chainMail->supply(
            fn(): \Noem\State\Chains\EnhanceRegionBuilder => new \Noem\State\Chains\EnhanceRegionBuilder(),
            fn(ConnectedRegions $c, Events $e): \Noem\State\Chains\DispatchAction => new \Noem\State\Chains\DispatchAction($c, $e),
            fn(): \Noem\State\Chains\ValidateCallback => new \Noem\State\Chains\ValidateCallback(),
            fn(): \Noem\State\Chains\PrepareInvokable => new \Noem\State\Chains\PrepareInvokable(),
            fn(): \Noem\State\Chains\InvokeCallback => new \Noem\State\Chains\InvokeCallback(),
            fn(): ConnectedRegions => new ConnectedRegions(),
            fn(): ExtendedState => new ExtendedState(),
            fn(ConnectedRegions $connectedRegions): \Noem\State\Chains\Meta => new \Noem\State\Chains\Meta($connectedRegions),
            fn(): \Noem\State\Chains\Set => new \Noem\State\Chains\Set(),
            fn(): \Noem\State\Chains\Get => new \Noem\State\Chains\Get(),
            fn(): \Noem\State\Chains\Notification => new \Noem\State\Chains\Notification(),
            Events::conjure(),
            fn(ConnectedRegions $connections): \Noem\State\Chains\Path => new \Noem\State\Chains\Path($connections)
        );
        return $chainMail;
    }

    public function testBuilderAcceptsCustomChainMail(): void
    {
        $customChainMail = $this->createConfiguredChainMail();

        $builder = new RegionBuilder($customChainMail);

        $this->assertInstanceOf(RegionBuilder::class, $builder);
        $this->assertSame($customChainMail, $builder->chainMail);
    }

    public function testCustomChainMailIsUsedForServices(): void
    {
        $customChainMail = $this->createConfiguredChainMail();

        $builder = new RegionBuilder($customChainMail);
        
        // Verify builder can be built successfully with custom ChainMail
        $builder->setStates('idle');
        $region = $builder->build();
        
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testBuilderDoesNotOverrideCustomChainMail(): void
    {
        $customChainMail = $this->createConfiguredChainMail();
        $builder = new RegionBuilder($customChainMail);

        // Builder should use the custom ChainMail as-is without modifying it
        $this->assertSame($customChainMail, $builder->chainMail);
        
        // Verify builder works with custom ChainMail
        $builder->setStates('idle');
        $region = $builder->build();
        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testMultipleBuildersCanShareChainMail(): void
    {
        $sharedChainMail = $this->createConfiguredChainMail();
        
        $builder1 = new RegionBuilder($sharedChainMail);
        $builder2 = new RegionBuilder($sharedChainMail);
        
        $this->assertSame($sharedChainMail, $builder1->chainMail);
        $this->assertSame($sharedChainMail, $builder2->chainMail);
        $this->assertSame($builder1->chainMail, $builder2->chainMail);
    }
}
