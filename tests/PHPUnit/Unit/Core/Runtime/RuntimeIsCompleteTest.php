<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime.isComplete() returns true when region is in final state
 */
#[Group('runtime')]
#[Group('runtime-queries')]
class RuntimeIsCompleteTest extends TestCase
{
    public function testIsCompleteReturnsFalseBeforeExecution(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->build();

        $runtime = new StandardRuntime($region);

        $this->assertFalse($runtime->isComplete(), 'Should not be complete before execution');
    }

    public function testIsCompleteReturnsFalseWhileRunning(): void
    {
        $isCompleteDuringExecution = null;

        $region = (new RegionBuilder())
            ->setStates('start', 'middle', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'middle'))
            ->addBuildStep(new AddTransition('middle', 'done'))
            ->onAction('start', function (object $t) use (&$runtime, &$isCompleteDuringExecution): void {
                $isCompleteDuringExecution = $runtime->isComplete();
            })
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertFalse($isCompleteDuringExecution, 'Should return false during execution');
    }

    public function testIsCompleteReturnsTrueWhenRegionInFinalState(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);
        $runtime->run();

        $this->assertTrue($runtime->isComplete(), 'Should return true when region completed');
    }

    public function testIsCompleteReturnsTrueAfterStepBasedCompletion(): void
    {
        $region = (new RegionBuilder())
            ->setStates('counting', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('counting', 'done', function (object $t): bool {
                static $count = 0;
                $count++;
                return $count >= 2;
            }))
            ->build();

        $runtime = new StandardRuntime($region);

        $runtime->run(steps: 1);
        $this->assertFalse($runtime->isComplete(), 'Not complete after step 1');

        $runtime->run(steps: 1);
        $this->assertTrue($runtime->isComplete(), 'Should be complete after step 2');
    }

    public function testIsCompleteMatchesRegionIsFinal(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $this->assertEquals($region->isFinal(), $runtime->isComplete(), 'isComplete should match region->isFinal() before run');

        $runtime->run();

        $this->assertEquals($region->isFinal(), $runtime->isComplete(), 'isComplete should match region->isFinal() after run');
    }
}
