<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Built region uses first state as initial when none is marked
 */
#[Group('region-builder')]
#[Group('state-configuration')]
class DefaultInitialStateTest extends TestCase
{
    public function testFirstStateIsDefaultInitial(): void
    {
        $builder = new RegionBuilder();

        $builder->setStates('first', 'second', 'third');

        $region = $builder->build();

        $this->assertTrue($region->isInState('first'), 'Region should default to first state as initial');
        $this->assertFalse($region->isInState('second'));
        $this->assertFalse($region->isInState('third'));
    }

    public function testDefaultInitialStateWithAddState(): void
    {
        $builder = new RegionBuilder();

        $builder->addState('alpha')
                ->addState('beta')
                ->addState('gamma');

        $region = $builder->build();

        $this->assertTrue($region->isInState('alpha'));
    }
}
