<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\BuildStep;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: A builder can add custom build steps using addBuildStep with BuildStep interface
 */
#[Group('region-builder')]
#[Group('build-steps')]
class AddBuildStepTest extends TestCase
{
    public function testAddBuildStepAcceptsBuildStepInterface(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $buildStep = new class implements BuildStep {
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                return $next($builder);
            }
        };

        $result = $builder->addBuildStep($buildStep);

        $this->assertSame($builder, $result, 'addBuildStep should return builder for chaining');

        $region = $builder->build();
        $this->assertInstanceOf(Region::class, $region);
    }

    public function testAddBuildStepCanBeCalledMultipleTimes(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $stepOne = new class implements BuildStep {
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                return $next($builder);
            }
        };

        $stepTwo = new class implements BuildStep {
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                return $next($builder);
            }
        };

        $builder->addBuildStep($stepOne)
                ->addBuildStep($stepTwo);

        $region = $builder->build();
        $this->assertInstanceOf(Region::class, $region);
    }

    public function testBuildStepIsInvokedDuringBuild(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $stepInvoked = false;

        $buildStep = new class ($stepInvoked) implements BuildStep {
            public function __construct(private bool &$invoked)
            {
            }

            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->invoked = true;
                return $next($builder);
            }
        };

        $builder->addBuildStep($buildStep);

        $this->assertFalse($stepInvoked, 'Build step should not be invoked before build()');

        $region = $builder->build();

        $this->assertTrue($stepInvoked, 'Build step should be invoked during build()');
        $this->assertInstanceOf(Region::class, $region);
    }
}
