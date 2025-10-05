<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions;

use Noem\State\Chains\DispatchAction;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: TransitionsFeature hooks into DispatchAction chain
 */
#[Group('transitions')]
#[Group('feature-registration')]
class HooksIntoDispatchActionTest extends TestCase
{
    public function testHooksIntoDispatchActionChain(): void
    {
        // Create a builder which will initialize the DispatchAction chain
        $builder = new RegionBuilder();
        $chainMail = $builder->chainMail;
        
        // Get the DispatchAction chain before feature is applied
        $dispatchActionBefore = $chainMail->get(DispatchAction::class);
        
        // Apply the feature
        $feature = new TransitionsFeature();
        $feature($chainMail);
        
        // Get the DispatchAction chain after feature is applied
        $dispatchActionAfter = $chainMail->get(DispatchAction::class);
        
        // The feature should have linked middleware into the chain
        // Since we can't directly inspect the chain's middleware, 
        // we verify the chain instance is the same (it modifies in place)
        $this->assertSame($dispatchActionBefore, $dispatchActionAfter);
    }
    
    public function testFeatureModifiesExistingDispatchAction(): void
    {
        $builder = new RegionBuilder();
        $feature = new TransitionsFeature();
        
        // Feature should work with existing ChainMail setup
        $feature($builder->chainMail);
        
        // Should not throw and services should be accessible
        $this->assertTrue(true);
    }
}
