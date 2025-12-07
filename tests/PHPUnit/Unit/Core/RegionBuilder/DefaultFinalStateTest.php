<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Built region uses last state as final when none is marked
 */
#[Group('region-builder')]
#[Group('state-configuration')]
class DefaultFinalStateTest extends TestCase
{
    public function testLastStateIsDefaultFinal(): void
    {
        $builder = new RegionBuilder();

        $builder->setStates('first', 'second', 'third');

        // Add transitions using AddTransition build step
        $builder->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition('first', 'second', fn(object $t): bool => true))
                ->addBuildStep(new \Noem\State\Feature\Transitions\AddTransition('second', 'third', fn(object $t): bool => true));

        $region = $builder->build();

        $this->assertFalse($region->isFinal(), 'Region should not be final initially');

        // Transition to second
        $region->trigger((object)[]);
        $this->assertFalse($region->isFinal(), 'Region should not be final in second state');

        // Transition to third (last state)
        $region->trigger((object)[]);
        $this->assertTrue($region->isFinal(), 'Region should be final in last state when no final marked');
    }
}
