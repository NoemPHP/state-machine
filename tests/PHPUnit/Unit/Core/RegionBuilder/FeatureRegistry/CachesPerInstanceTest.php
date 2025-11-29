<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\Feature;
use Noem\State\Feature\FeatureRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: FeatureRegistry resolve caches results per ChainMail instance using SplObjectStorage
 */
#[Group('feature-dependency-resolution')]
class CachesPerInstanceTest extends TestCase
{
    public function testResolveCachesResultPerChainMailInstance(): void
    {
        $registry = new FeatureRegistry();
        $chainMailA = new ChainMail();
        $chainMailB = new ChainMail();

        $feature = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void {}
        };

        $registry->register($feature);

        // First call for instance A
        $resultA1 = $registry->resolve($chainMailA);

        // Second call for instance A (should return cached)
        $resultA2 = $registry->resolve($chainMailA);

        // Call for different instance B
        $resultB = $registry->resolve($chainMailB);

        // Same instance should return same array reference
        $this->assertSame($resultA1, $resultA2,
            'Subsequent calls with same ChainMail should return cached array');

        // Different instances get same features but the behavior is what matters
        $this->assertCount(1, $resultA1);
        $this->assertCount(1, $resultB);
        $this->assertContains($feature, $resultA1);
        $this->assertContains($feature, $resultB);
    }

    public function testCachePreventsDuplicateResolution(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();
        $resolutionCount = 0;

        // Use reflection or indirect observation since resolution is internal
        // We'll track invocations which should only happen once
        $invocationCount = 0;

        $feature = new class($invocationCount) implements Feature {
            public function __construct(private &$invocationCount) {}

            public function __invoke(ChainMail $chainMail): void {
                $this->invocationCount++;
            }
        };

        $registry->register($feature);

        // Multiple calls
        $registry->resolve($chainMail);
        $registry->resolve($chainMail);
        $registry->resolve($chainMail);

        // Should only invoke once
        $this->assertEquals(1, $invocationCount,
            'Feature should only be invoked once despite multiple resolve calls');
    }

    public function testCacheIsPerInstanceNotGlobal(): void
    {
        $registry = new FeatureRegistry();
        $chainMailA = new ChainMail();
        $chainMailB = new ChainMail();
        $chainMailC = new ChainMail();

        $invocationsPerInstance = [];

        $feature = new class($invocationsPerInstance) implements Feature {
            public function __construct(private &$invocationsPerInstance) {}

            public function __invoke(ChainMail $chainMail): void {
                $id = spl_object_id($chainMail);
                $this->invocationsPerInstance[$id] = ($this->invocationsPerInstance[$id] ?? 0) + 1;
            }
        };

        $registry->register($feature);

        // Resolve for each instance
        $registry->resolve($chainMailA);
        $registry->resolve($chainMailB);
        $registry->resolve($chainMailC);

        // Each instance should have exactly one invocation
        $this->assertEquals([
            spl_object_id($chainMailA) => 1,
            spl_object_id($chainMailB) => 1,
            spl_object_id($chainMailC) => 1,
        ], $invocationsPerInstance,
            'Each ChainMail instance should trigger exactly one invocation');
    }
}
