<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Builder throws RuntimeException when states array is empty
 */
#[Group('region-builder')]
#[Group('validation')]
class EmptyStatesValidationTest extends TestCase
{
    public function testBuildThrowsExceptionWithEmptyStates(): void
    {
        $builder = new RegionBuilder();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('States cannot be empty');

        $builder->build();
    }

    public function testBuildSucceedsWithAtLeastOneState(): void
    {
        $builder = new RegionBuilder();

        $builder->setStates('single-state');

        $region = $builder->build();

        $this->assertTrue($region->isInState('single-state'));
    }
}
