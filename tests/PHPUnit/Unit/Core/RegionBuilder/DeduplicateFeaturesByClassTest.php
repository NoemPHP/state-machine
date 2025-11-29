<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Duplicate feature registration by class name is ignored
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class DeduplicateFeaturesByClassTest extends TestCase
{
    public function testDuplicateFeatureRegistrationIsIgnored(): void
    {
        $invocationCount = 0;

        $featureClass = new class($invocationCount) implements Feature {
            public function __construct(private int &$count) {}

            public function __invoke(ChainMail $chainMail): void
            {
                $this->count++;
            }
        };

        $builder = new RegionBuilder();
        $builder->enableFeatures(
            $featureClass,
            $featureClass  // Duplicate instance
        );
        $builder->setStates('idle')->build();

        $this->assertSame(
            1,
            $invocationCount,
            'Feature should only be invoked once even when enabled multiple times'
        );
    }

    public function testMultipleDuplicatesAreAllIgnored(): void
    {
        $invocationCount = 0;

        $featureClass = new class($invocationCount) implements Feature {
            public function __construct(private int &$count) {}

            public function __invoke(ChainMail $chainMail): void
            {
                $this->count++;
            }
        };

        $builder = new RegionBuilder();
        $builder
            ->enableFeatures($featureClass)
            ->enableFeatures($featureClass)  // Second call
            ->enableFeatures($featureClass); // Third call

        $builder->setStates('idle')->build();

        $this->assertSame(
            1,
            $invocationCount,
            'Feature should only be invoked once across multiple enableFeatures calls'
        );
    }
}
