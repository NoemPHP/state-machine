<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\Transitions;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Transitions respect final state boundaries
 */
#[Group('transitions')]
#[Group('integration')]
class FinalStateBoundaryTest extends TestCase
{
    public function testRespectsFinalStateBoundary(): void
    {
        $builder = new RegionBuilder();
        $enterCount = 0;

        $region = $builder
            ->setStates('one', 'two', 'three', 'final')
            ->markInitial('one')
            ->markFinal('final')
            ->addBuildStep(new AddTransition('one', 'two'))
            ->addBuildStep(new AddTransition('two', 'three'))
            ->addBuildStep(new AddTransition('three', 'final'))
            // This should never happen
            ->addBuildStep(new AddTransition('final', 'one'))
            ->onEnter('one', function (object $t) use (&$enterCount) {
                $enterCount++;
            })
            ->build();

        // Initial state entry doesn't fire onEnter during build
        $this->assertEquals(0, $enterCount);

        // Progress to final
        $region->trigger((object)[]);
        $region->trigger((object)[]);
        $region->trigger((object)[]);

        $this->assertTrue($region->isFinal());
        $this->assertTrue($region->isInState('final'));

        // Try to trigger more - should stay in final
        $region->trigger((object)[]);
        $region->trigger((object)[]);

        $this->assertTrue($region->isInState('final'));
        $this->assertEquals(1, $enterCount, 'Should not re-enter initial state from final');
    }

    public function testWorkflowCompletionDetection(): void
    {
        $builder = new RegionBuilder();
        $completionCallbackCalled = false;

        $region = $builder
            ->setStates('pending', 'processing', 'complete')
            ->markInitial('pending')
            ->markFinal('complete')
            ->addBuildStep(new AddTransition('pending', 'processing'))
            ->addBuildStep(new AddTransition('processing', 'complete'))
            ->onEnter('complete', function (object $t) use (&$completionCallbackCalled) {
                $completionCallbackCalled = true;
            })
            ->build();

        $this->assertFalse($region->isFinal());
        $this->assertFalse($completionCallbackCalled);

        // Progress through workflow
        $region->trigger((object)[]);
        $this->assertFalse($region->isFinal());

        $region->trigger((object)[]);
        $this->assertTrue($region->isFinal());
        $this->assertTrue($completionCallbackCalled);
    }
}
