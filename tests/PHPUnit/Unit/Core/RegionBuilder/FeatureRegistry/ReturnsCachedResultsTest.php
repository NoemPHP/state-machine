<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\Feature;
use Noem\State\Feature\FeatureRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: FeatureRegistry resolve returns cached results without re-invoking on subsequent calls
 */
#[Group('feature-dependency-resolution')]
class ReturnsCachedResultsTest extends TestCase
{
    public function testSubsequentCallsReturnCachedResultsWithoutReinvoking(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();
        $invocationCount = 0;

        $feature = new class ($invocationCount) implements Feature {
            public function __construct(private &$invocationCount)
            {
            }

            public function __invoke(ChainMail $chainMail): void
            {
                $this->invocationCount++;
            }
        };

        $registry->register($feature);

        // First call
        $result1 = $registry->resolve($chainMail);
        $this->assertEquals(1, $invocationCount, 'First call should invoke feature');

        // Second call - should return cached
        $result2 = $registry->resolve($chainMail);
        $this->assertEquals(1, $invocationCount, 'Second call should NOT invoke feature again');

        // Third call - still cached
        $result3 = $registry->resolve($chainMail);
        $this->assertEquals(1, $invocationCount, 'Third call should NOT invoke feature again');

        // All results should be same array reference
        $this->assertSame($result1, $result2);
        $this->assertSame($result2, $result3);
    }

    public function testCachedResultsContainSameFeatures(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();

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

        $result1 = $registry->resolve($chainMail);
        $result2 = $registry->resolve($chainMail);

        $this->assertCount(2, $result1);
        $this->assertCount(2, $result2);
        $this->assertSame($result1, $result2, 'Cached result should be identical');
    }

    public function testCachePreventsDuplicateMiddlewareRegistration(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();
        $middlewareRegistrations = [];

        $feature = new class ($middlewareRegistrations) implements Feature {
            public function __construct(private &$middlewareRegistrations)
            {
            }

            public function __invoke(ChainMail $chainMail): void
            {
                // Simulate middleware registration
                $this->middlewareRegistrations[] = 'middleware_registered';
            }
        };

        $registry->register($feature);

        // Multiple resolve calls
        $registry->resolve($chainMail);
        $registry->resolve($chainMail);
        $registry->resolve($chainMail);

        // Should only register middleware once
        $this->assertEquals(
            ['middleware_registered'],
            $middlewareRegistrations,
            'Middleware should only be registered once despite multiple resolve calls'
        );
    }
}
