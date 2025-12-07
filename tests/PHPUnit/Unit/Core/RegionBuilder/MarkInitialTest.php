<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can mark a state as initial using markInitial
 */
#[Group('region-builder')]
#[Group('state-configuration')]
class MarkInitialTest extends TestCase
{
    public function testMarkInitialSetsStartingState(): void
    {
        $builder = new RegionBuilder();

        $result = $builder->setStates('idle', 'processing', 'complete')
                          ->markInitial('processing');

        $this->assertSame($builder, $result, 'markInitial should return builder for chaining');

        $region = $builder->build();

        $this->assertTrue($region->isInState('processing'), 'Region should start in marked initial state');
        $this->assertFalse($region->isInState('idle'));
    }

    public function testMarkInitialOverridesDefaultBehavior(): void
    {
        $builder = new RegionBuilder();

        $builder->setStates('first', 'second', 'third')
                ->markInitial('third');

        $region = $builder->build();

        $this->assertTrue($region->isInState('third'));
        $this->assertFalse($region->isInState('first'));
    }
}
