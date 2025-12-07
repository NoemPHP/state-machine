<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\BuildStep;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Build step callbacks receive builder, next, and first parameters
 */
#[Group('region-builder')]
#[Group('build-steps')]
class BuildStepCallbacksTest extends TestCase
{
    public function testBuildStepReceivesBuilderParameter(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $receivedBuilder = null;

        $buildStep = new class ($receivedBuilder) implements BuildStep {
            public function __construct(private mixed &$received)
            {
            }

            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->received = $builder;
                return $next($builder);
            }
        };

        $builder->addBuildStep($buildStep);
        $builder->build();

        $this->assertInstanceOf(
            RegionBuilder::class,
            $receivedBuilder,
            'Build step should receive RegionBuilder instance'
        );
    }

    public function testBuildStepReceivesNextCallback(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $receivedNext = null;

        $buildStep = new class ($receivedNext) implements BuildStep {
            public function __construct(private mixed &$received)
            {
            }

            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->received = $next;
                return $next($builder);
            }
        };

        $builder->addBuildStep($buildStep);
        $builder->build();

        $this->assertIsCallable($receivedNext, 'Build step should receive callable next parameter');
    }

    public function testBuildStepReceivesFirstCallback(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $receivedFirst = null;

        $buildStep = new class ($receivedFirst) implements BuildStep {
            public function __construct(private mixed &$received)
            {
            }

            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $this->received = $first;
                return $next($builder);
            }
        };

        $builder->addBuildStep($buildStep);
        $builder->build();

        $this->assertIsCallable($receivedFirst, 'Build step should receive callable first parameter');
    }

    public function testNextCallbackContinuesChain(): void
    {
        $builder = new RegionBuilder();
        $builder->setStates('idle');

        $nextCalled = false;

        $buildStep = new class ($nextCalled) implements BuildStep {
            public function __construct(private bool &$called)
            {
            }

            public function callback(RegionBuilder $builder, callable $next, callable $first): Region
            {
                $result = $next($builder);
                $this->called = true;
                return $result;
            }
        };

        $builder->addBuildStep($buildStep);
        $region = $builder->build();

        $this->assertTrue($nextCalled, 'Calling next should continue the build chain');
        $this->assertInstanceOf(Region::class, $region);
    }
}
