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
 * Acceptance Criterion: DoTransition fires onEnterState event after transition
 */
#[Group('transitions')]
#[Group('transition-execution')]
class FiresOnEnterStateTest extends TestCase
{
    public function testFiresOnEnterAfterTransition(): void
    {
        $enterFired = false;
        $trigger = new stdClass();
        
        $region = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('start', 'end')
            ->onEnter('end', function () use (&$enterFired) {
                $enterFired = true;
            })
            ->addBuildStep(new AddTransition('start', 'end'))
            ->build();
        
        $region->trigger($trigger);
        
        $this->assertTrue($enterFired);
        $this->assertTrue($region->isInState('end'));
    }
}
