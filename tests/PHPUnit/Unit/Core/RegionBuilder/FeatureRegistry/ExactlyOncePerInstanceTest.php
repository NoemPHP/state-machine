<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\Feature;
use Noem\State\Feature\FeatureRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: FeatureRegistry resolve invokes features exactly once per ChainMail instance
 */
#[Group('feature-dependency-resolution')]
class ExactlyOncePerInstanceTest extends TestCase
{
    public function testFeatureInvokedExactlyOncePerChainMailInstance(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();
        $invocationCount = 0;

        $feature = new class($invocationCount) implements Feature {
            public function __construct(private &$invocationCount) {}

            public function __invoke(ChainMail $chainMail): void {
                $this->invocationCount++;
            }
        };

        $registry->register($feature);

        // Call resolve many times
        for ($i = 0; $i < 10; $i++) {
            $registry->resolve($chainMail);
        }

        $this->assertEquals(1, $invocationCount,
            'Feature should be invoked exactly once regardless of number of resolve calls');
    }

    public function testMultipleFeaturesEachInvokedExactlyOnce(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();
        $invocations = ['A' => 0, 'B' => 0, 'C' => 0];

        $featureA = new class($invocations) implements Feature {
            public function __construct(private &$invocations) {}
            public function __invoke(ChainMail $chainMail): void {
                $this->invocations['A']++;
            }
        };

        $featureB = new class($invocations) implements Feature {
            public function __construct(private &$invocations) {}
            public function __invoke(ChainMail $chainMail): void {
                $this->invocations['B']++;
            }
        };

        $featureC = new class($invocations) implements Feature {
            public function __construct(private &$invocations) {}
            public function __invoke(ChainMail $chainMail): void {
                $this->invocations['C']++;
            }
        };

        $registry->register($featureA);
        $registry->register($featureB);
        $registry->register($featureC);

        // Multiple resolve calls
        $registry->resolve($chainMail);
        $registry->resolve($chainMail);
        $registry->resolve($chainMail);

        $this->assertEquals(['A' => 1, 'B' => 1, 'C' => 1], $invocations,
            'Each feature should be invoked exactly once');
    }

    public function testExactlyOnceGuaranteePreventsDuplicateMiddleware(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();
        $middlewareStack = [];

        $feature = new class($middlewareStack) implements Feature {
            public function __construct(private &$middlewareStack) {}

            public function __invoke(ChainMail $chainMail): void {
                // Simulate registering middleware on a chain
                $this->middlewareStack[] = 'middleware_layer';
            }
        };

        $registry->register($feature);

        // This simulates the bug scenario: child.build() called after parent.build()
        // Both share the same ChainMail
        $registry->resolve($chainMail);  // First build (parent)
        $registry->resolve($chainMail);  // Second build (child)

        // Should only have one middleware layer, not two
        $this->assertEquals(['middleware_layer'], $middlewareStack,
            'Exactly-once guarantee prevents duplicate middleware registration in shared ChainMail');
    }

    public function testDifferentInstancesGetIndependentExactlyOnceGuarantee(): void
    {
        $registry = new FeatureRegistry();
        $chainMailA = new ChainMail();
        $chainMailB = new ChainMail();

        $invocationsPerInstance = [];

        $feature = new class($invocationsPerInstance) implements Feature {
            public function __construct(private &$invocationsPerInstance) {}

            public function __invoke(ChainMail $chainMail): void {
                $id = spl_object_id($chainMail);
                $this->invocationsPerInstance[$id] = ($this->invocationsPerInstance[$id] ?? 0) + 1;
            }
        };

        $registry->register($feature);

        // Multiple calls for instance A
        $registry->resolve($chainMailA);
        $registry->resolve($chainMailA);
        $registry->resolve($chainMailA);

        // Multiple calls for instance B
        $registry->resolve($chainMailB);
        $registry->resolve($chainMailB);

        // Each instance should have exactly one invocation
        $this->assertEquals(1, $invocationsPerInstance[spl_object_id($chainMailA)],
            'ChainMail A should have exactly one invocation');
        $this->assertEquals(1, $invocationsPerInstance[spl_object_id($chainMailB)],
            'ChainMail B should have exactly one invocation');
    }
}
