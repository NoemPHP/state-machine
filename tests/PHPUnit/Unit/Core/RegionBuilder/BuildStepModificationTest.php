<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\BuildStep;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Build steps can modify the region during construction
 */
#[Group('region-builder')]
#[Group('build-steps')]
class BuildStepModificationTest extends TestCase
{
    public function testBuildStepCanModifyBuilder(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $buildStep = new class implements BuildStep {
            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                // Modify builder by adding a state
                $builder->addState('processing');
                return $next($builder);
            }
        };

        $builder->addBuildStep($buildStep);
        $region = $builder->build();

        $this->assertInstanceOf(Region::class, $region);
    }

    public function testBuildStepCanModifyRegionAfterConstruction(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $regionModified = false;

        $buildStep = new class ($regionModified) implements BuildStep {
            public function __construct(private bool &$modified)
            {
            }

            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $region = $next($builder);

                // Mark that we accessed the region
                $this->modified = $region instanceof Region;

                return $region;
            }
        };

        $builder->addBuildStep($buildStep);
        $region = $builder->build();

        $this->assertTrue($regionModified, 'Build step should have access to region after construction');
        $this->assertInstanceOf(Region::class, $region);
    }

    public function testMultipleBuildStepsCanModifySequentially(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $modifications = [];

        $stepOne = new class ($modifications) implements BuildStep {
            public function __construct(private array &$mods)
            {
            }

            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->mods[] = 'step1_before';
                $builder->addState('step1');
                $region = $next($builder);
                $this->mods[] = 'step1_after';
                return $region;
            }
        };

        $stepTwo = new class ($modifications) implements BuildStep {
            public function __construct(private array &$mods)
            {
            }

            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->mods[] = 'step2_before';
                $builder->addState('step2');
                $region = $next($builder);
                $this->mods[] = 'step2_after';
                return $region;
            }
        };

        $builder->addBuildStep($stepOne)
                ->addBuildStep($stepTwo);

        $region = $builder->build();

        $this->assertEquals(
            ['step1_before', 'step2_before', 'step2_after', 'step1_after'],
            $modifications,
            'Build steps should execute in LIFO order (middleware pattern)'
        );
        $this->assertInstanceOf(Region::class, $region);
    }
}
