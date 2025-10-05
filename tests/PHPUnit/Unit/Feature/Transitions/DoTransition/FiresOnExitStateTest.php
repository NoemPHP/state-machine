<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\DoTransition;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Acceptance Criterion: DoTransition fires onExitState event before transition
 */
#[Group('transitions')]
#[Group('transition-execution')]
class FiresOnExitStateTest extends TestCase
{
    public function testFiresOnExitBeforeTransition(): void
    {
        $exitFired = false;
        $trigger = new stdClass();
        
        $region = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('start', 'end')
            ->onExit('start', function (object $t) use (&$exitFired) {
                $exitFired = true;
            })
            ->addBuildStep(new AddTransition('start', 'end'))
            ->build();
        
        $region->trigger($trigger);
        
        $this->assertTrue($exitFired);
        $this->assertTrue($region->isInState('end'));
    }
}
