<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Chains\ConnectedRegions;
use Noem\State\Chains\DispatchAction;
use Noem\State\Chains\EnhanceRegionBuilder;
use Noem\State\Chains\ExtendedState;
use Noem\State\Chains\Get;
use Noem\State\Chains\InvokeCallback;
use Noem\State\Chains\Meta;
use Noem\State\Chains\Path;
use Noem\State\Chains\PrepareInvokable;
use Noem\State\Chains\Set;
use Noem\State\Chains\ValidateCallback;
use Noem\State\Events;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Builder constructor registers default service providers in ChainMail
 */
#[Group('region-builder')]
#[Group('chainmail-integration')]
class DefaultServicesTest extends TestCase
{
    public function testBuilderRegistersItselfInChainMail(): void
    {
        $builder = new RegionBuilder();
        
        $retrievedBuilder = $builder->chainMail->get(RegionBuilder::class);
        
        $this->assertSame($builder, $retrievedBuilder, 
            'Builder should register itself in ChainMail');
    }
    
    public function testBuilderRegistersEnhanceRegionBuilderChain(): void
    {
        $builder = new RegionBuilder();
        
        $enhance = $builder->chainMail->get(EnhanceRegionBuilder::class);
        
        $this->assertInstanceOf(EnhanceRegionBuilder::class, $enhance);
    }
    
    public function testBuilderRegistersDispatchActionChain(): void
    {
        $builder = new RegionBuilder();
        
        $dispatchAction = $builder->chainMail->get(DispatchAction::class);
        
        $this->assertInstanceOf(DispatchAction::class, $dispatchAction);
    }
    
    public function testBuilderRegistersValidationChains(): void
    {
        $builder = new RegionBuilder();
        
        $validateCallback = $builder->chainMail->get(ValidateCallback::class);
        $prepareInvokable = $builder->chainMail->get(PrepareInvokable::class);
        $invokeCallback = $builder->chainMail->get(InvokeCallback::class);
        
        $this->assertInstanceOf(ValidateCallback::class, $validateCallback);
        $this->assertInstanceOf(PrepareInvokable::class, $prepareInvokable);
        $this->assertInstanceOf(InvokeCallback::class, $invokeCallback);
    }
    
    public function testBuilderRegistersConnectionChains(): void
    {
        $builder = new RegionBuilder();
        
        $connectedRegions = $builder->chainMail->get(ConnectedRegions::class);
        $meta = $builder->chainMail->get(Meta::class);
        $path = $builder->chainMail->get(Path::class);
        
        $this->assertInstanceOf(ConnectedRegions::class, $connectedRegions);
        $this->assertInstanceOf(Meta::class, $meta);
        $this->assertInstanceOf(Path::class, $path);
    }
    
    public function testBuilderRegistersStateManagementChains(): void
    {
        $builder = new RegionBuilder();
        
        $extendedState = $builder->chainMail->get(ExtendedState::class);
        $set = $builder->chainMail->get(Set::class);
        $get = $builder->chainMail->get(Get::class);
        
        $this->assertInstanceOf(ExtendedState::class, $extendedState);
        $this->assertInstanceOf(Set::class, $set);
        $this->assertInstanceOf(Get::class, $get);
    }
    
    public function testBuilderRegistersEventsService(): void
    {
        $builder = new RegionBuilder();
        
        $events = $builder->chainMail->get(Events::class);
        
        $this->assertInstanceOf(Events::class, $events);
    }
}
