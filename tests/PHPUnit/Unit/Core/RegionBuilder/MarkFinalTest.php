<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can mark a state as final using markFinal
 */
#[Group('region-builder')]
#[Group('state-configuration')]
class MarkFinalTest extends TestCase
{
    public function testMarkFinalSetsFinalState(): void
    {
        $builder = new RegionBuilder();
        
        $result = $builder->setStates('idle', 'processing', 'complete')
                          ->markFinal('processing');
        
        $this->assertSame($builder, $result, 'markFinal should return builder for chaining');
        
        $region = $builder->build();
        
        $this->assertFalse($region->isFinal(), 'Region should not be final initially');
    }
    
    public function testMarkFinalCanBeQueriedAfterTransition(): void
    {
        $builder = new RegionBuilder();
        
        $builder->setStates('start', 'end')
                ->markInitial('start')
                ->markFinal('end');

        // Add transition using AddTransition build step
        $builder->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition('start', 'end', fn(object $t): bool => true));
        
        $region = $builder->build();
        
        $this->assertFalse($region->isFinal(), 'Should not be final at start');
        
        $region->trigger((object)[]);
        
        $this->assertTrue($region->isFinal(), 'Should be final after reaching marked state');
    }
}
