<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\Feature;
use Noem\State\Feature\FeatureRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: FeatureRegistry resolve handles multiple independent ChainMail instances
 */
#[Group('feature-dependency-resolution')]
class MultipleIndependentInstancesTest extends TestCase
{
    public function testResolveHandlesMultipleChainMailInstancesIndependently(): void
    {
        $registry = new FeatureRegistry();
        $chainMailA = new ChainMail();
        $chainMailB = new ChainMail();
        $chainMailC = new ChainMail();

        $invocationsPerInstance = [];

        $feature = new class ($invocationsPerInstance) implements Feature {
            public function __construct(private &$invocationsPerInstance)
            {
            }

            public function __invoke(ChainMail $chainMail): void
            {
                $id = spl_object_id($chainMail);
                $this->invocationsPerInstance[$id] = true;
            }
        };

        $registry->register($feature);

        // Resolve for each instance independently
        $resultA = $registry->resolve($chainMailA);
        $resultB = $registry->resolve($chainMailB);
        $resultC = $registry->resolve($chainMailC);

        // Each should have been invoked
        $this->assertCount(
            3,
            $invocationsPerInstance,
            'Feature should be invoked once for each independent ChainMail instance'
        );

        $this->assertArrayHasKey(spl_object_id($chainMailA), $invocationsPerInstance);
        $this->assertArrayHasKey(spl_object_id($chainMailB), $invocationsPerInstance);
        $this->assertArrayHasKey(spl_object_id($chainMailC), $invocationsPerInstance);
    }

    public function testIndependentInstancesDoNotShareCache(): void
    {
        $registry = new FeatureRegistry();
        $chainMailA = new ChainMail();
        $chainMailB = new ChainMail();

        $feature = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void
            {
            }
        };

        $registry->register($feature);

        $resultA1 = $registry->resolve($chainMailA);
        $resultA2 = $registry->resolve($chainMailA);

        $resultB1 = $registry->resolve($chainMailB);
        $resultB2 = $registry->resolve($chainMailB);

        // Same instance should share cache
        $this->assertSame(
            $resultA1,
            $resultA2,
            'Same ChainMail instance should return cached result'
        );
        $this->assertSame(
            $resultB1,
            $resultB2,
            'Same ChainMail instance should return cached result'
        );

        // The important behavior: each instance processes features independently
        $this->assertCount(1, $resultA1);
        $this->assertCount(1, $resultB1);
    }

    public function testInterleavedResolveCallsWorkCorrectly(): void
    {
        $registry = new FeatureRegistry();
        $chainMailA = new ChainMail();
        $chainMailB = new ChainMail();

        $invocationLog = [];

        $feature = new class ($invocationLog) implements Feature {
            public function __construct(private &$invocationLog)
            {
            }

            public function __invoke(ChainMail $chainMail): void
            {
                $id = spl_object_id($chainMail);
                $this->invocationLog[] = "invoked_$id";
            }
        };

        $registry->register($feature);

        // Interleaved calls
        $registry->resolve($chainMailA);  // First A: invoke
        $registry->resolve($chainMailB);  // First B: invoke
        $registry->resolve($chainMailA);  // Second A: cached
        $registry->resolve($chainMailB);  // Second B: cached
        $registry->resolve($chainMailA);  // Third A: cached

        // Should only have two invocations (one per instance)
        $this->assertCount(
            2,
            $invocationLog,
            'Should only invoke once per ChainMail instance despite interleaved calls'
        );

        $this->assertStringContainsString('invoked_', $invocationLog[0]);
        $this->assertStringContainsString('invoked_', $invocationLog[1]);
    }

    public function testEachInstanceGetsCorrectFeatureArray(): void
    {
        $registry = new FeatureRegistry();
        $chainMailA = new ChainMail();
        $chainMailB = new ChainMail();

        $featureA = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void
            {
            }
        };

        $featureB = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void
            {
            }
        };

        $registry->register($featureA);
        $registry->register($featureB);

        $resultA = $registry->resolve($chainMailA);
        $resultB = $registry->resolve($chainMailB);

        // Both should have same features
        $this->assertCount(2, $resultA);
        $this->assertCount(2, $resultB);

        // Both should contain the same feature instances
        $this->assertContains($featureA, $resultA);
        $this->assertContains($featureB, $resultA);
        $this->assertContains($featureA, $resultB);
        $this->assertContains($featureB, $resultB);
    }
}
