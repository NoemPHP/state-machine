<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Builder validates configuration in assertValidConfig during build
 */
#[Group('region-builder')]
#[Group('validation')]
class ConfigValidationTest extends TestCase
{
    public function testValidConfigurationBuildsSuccessfully(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing');

        $region = $builder->build();

        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testValidationOccursDuringBuild(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('state1', 'state2', 'state3');

        // If validation fails, build should throw
        // If it succeeds, we get a region
        $region = $builder->build();

        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testSingleStateIsValid(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('only_state');

        $region = $builder->build();

        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }

    public function testMultipleStatesAreValid(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('one', 'two', 'three', 'four', 'five');

        $region = $builder->build();

        $this->assertInstanceOf(\Noem\State\Region::class, $region);
    }
}
