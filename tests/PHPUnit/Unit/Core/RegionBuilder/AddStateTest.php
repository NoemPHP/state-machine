<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can add individual states using addState
 */
#[Group('region-builder')]
#[Group('state-configuration')]
class AddStateTest extends TestCase
{
    public function testAddStateSingleState(): void
    {
        $builder = new RegionBuilder();

        $result = $builder->addState('initial');

        $this->assertSame($builder, $result, 'addState should return builder for chaining');

        $region = $builder->build();

        $this->assertTrue($region->isInState('initial'));
    }

    public function testAddStateMultipleTimes(): void
    {
        $builder = new RegionBuilder();

        $builder->addState('first')
                ->addState('second')
                ->addState('third');

        $region = $builder->build();

        $this->assertTrue($region->isInState('first'), 'Region should start in first added state');
    }

    public function testAddStateCanBeMixedWithSetStates(): void
    {
        $builder = new RegionBuilder();

        $builder->setStates('one', 'two')
                ->addState('three');

        $region = $builder->build();

        $this->assertTrue($region->isInState('one'));
    }
}
