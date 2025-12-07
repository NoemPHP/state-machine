<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: enableFeatures registers features without immediate invocation
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class DeferredFeatureInvocationTest extends TestCase
{
    public function testEnableFeaturesDoesNotInvokeFeatureImmediately(): void
    {
        $builder = new RegionBuilder();
        $invoked = false;

        $feature = new class ($invoked) implements Feature {
            public function __construct(private bool &$invoked)
            {
            }

            public function __invoke(ChainMail $chainMail): void
            {
                $this->invoked = true;
            }
        };

        $builder->enableFeatures($feature);

        $this->assertFalse($invoked, 'Feature should NOT be invoked during enableFeatures()');
    }

    public function testFeatureIsInvokedDuringBuild(): void
    {
        $builder = new RegionBuilder();
        $invoked = false;

        $feature = new class ($invoked) implements Feature {
            public function __construct(private bool &$invoked)
            {
            }

            public function __invoke(ChainMail $chainMail): void
            {
                $this->invoked = true;
            }
        };

        $builder->enableFeatures($feature);
        $this->assertFalse($invoked, 'Feature not invoked yet');

        $builder->setStates('idle')->build();

        $this->assertTrue($invoked, 'Feature should be invoked during build()');
    }
}
