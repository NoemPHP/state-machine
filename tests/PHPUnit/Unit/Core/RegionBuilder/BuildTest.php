<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: build creates a Region instance from builder configuration
 */
#[Group('region-builder')]
#[Group('builder-lifecycle')]
class BuildTest extends TestCase
{
    public function testBuildCreatesRegionInstance(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle', 'processing', 'complete');

        $region = $builder->build();

        $this->assertInstanceOf(Region::class, $region);
    }

    public function testBuildUsesBuilderConfiguration(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('start', 'middle', 'end')
                ->markInitial('start')
                ->markFinal('end');

        $region = $builder->build();

        $this->assertTrue($region->isInState('start'), 'Region should start in configured initial state');
    }

    public function testBuildCanBeCalledMultipleTimes(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $region1 = $builder->build();
        $region2 = $builder->build();

        $this->assertInstanceOf(Region::class, $region1);
        $this->assertInstanceOf(Region::class, $region2);
        $this->assertNotSame($region1, $region2, 'Each build should create a new Region instance');
    }

    public function testBuildBootsChainMail(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $bootCalled = false;

        $builder->chainMail->use(function () use (&$bootCalled) {
            $bootCalled = true;
        });

        $this->assertFalse($bootCalled, 'ChainMail should not be booted before build');

        $region = $builder->build();

        $this->assertTrue($bootCalled, 'ChainMail should be booted during build');
        $this->assertInstanceOf(Region::class, $region);
    }

    public function testBuildExecutesBuildSteps(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $stepExecuted = false;

        $builder->addBuildStep(new class ($stepExecuted) implements \Noem\State\BuildStep {
            public function __construct(private bool &$executed)
            {
            }

            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->executed = true;
                return $next($builder);
            }
        });

        $region = $builder->build();

        $this->assertTrue($stepExecuted, 'Build steps should be executed during build');
        $this->assertInstanceOf(Region::class, $region);
    }
}
