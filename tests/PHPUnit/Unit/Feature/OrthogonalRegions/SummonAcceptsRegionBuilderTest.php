<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: summon() accepts RegionBuilder and returns spawned Runtime
 */
#[Group('orthogonal-regions')]
#[Group('summon')]
class SummonAcceptsRegionBuilderTest extends TestCase
{
    public function testSummonAcceptsRegionBuilder(): void
    {
        $summonSucceeded = false;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$summonSucceeded) {
                try {
                    $this->summon($childBuilder);
                    $summonSucceeded = true;
                } catch (\Throwable $e) {
                    $summonSucceeded = false;
                }
            })
            ->build();

        $region->init();

        $this->assertTrue($summonSucceeded, 'summon() should accept RegionBuilder');
    }

    public function testSummonReturnsRuntime(): void
    {
        $returnedValue = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$returnedValue) {
                $returnedValue = $this->summon($childBuilder);
            })
            ->build();

        $region->init();

        $this->assertInstanceOf(\Noem\State\Runtime::class, $returnedValue);
    }

    public function testSummonedRuntimeIsExecutable(): void
    {
        $childExecuted = false;

        $childBuilder = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onAction('child', function (object $t) use (&$childExecuted) {
                $childExecuted = true;
                return 'done';
            });

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $runtime = $this->summon($childBuilder);
                $runtime->run();
            })
            ->build();

        $region->init();

        $this->assertTrue($childExecuted, 'Summoned runtime should be executable');
    }

    public function testSummonBuildsRegionFromBuilder(): void
    {
        $builtRegion = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child_state')
            ->markInitial('child_state');

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$builtRegion) {
                $runtime = $this->summon($childBuilder);
                $builtRegion = $runtime->getRegion();
            })
            ->build();

        $region->init();

        $this->assertNotNull($builtRegion);
        $this->assertEquals('child_state', $builtRegion->currentState());
    }

    public function testSummonCanBeCalledMultipleTimes(): void
    {
        $summonCount = 0;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child');

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$summonCount) {
                $this->summon($childBuilder);
                $summonCount++;
                $this->summon($childBuilder);
                $summonCount++;
                $this->summon($childBuilder);
                $summonCount++;
            })
            ->build();

        $region->init();

        $this->assertEquals(3, $summonCount, 'summon() should be callable multiple times');
    }
}
