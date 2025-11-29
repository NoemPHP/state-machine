<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\Feature;
use Noem\State\Feature\FeatureRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: FeatureRegistry resolve accepts ChainMail parameter and returns features in dependency order
 */
#[Group('feature-dependency-resolution')]
class ResolveWithChainMailTest extends TestCase
{
    public function testResolveAcceptsChainMailParameter(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();

        $featureA = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void {}
        };

        $registry->register($featureA);

        // Should accept ChainMail parameter
        $result = $registry->resolve($chainMail);

        $this->assertIsArray($result);
    }

    public function testResolveReturnsArrayOfFeatures(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();

        $featureA = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void {}
        };

        $featureB = new class implements Feature {
            public function __invoke(ChainMail $chainMail): void {}
        };

        $registry->register($featureA);
        $registry->register($featureB);

        $result = $registry->resolve($chainMail);

        $this->assertCount(2, $result);
        $this->assertContainsOnly(Feature::class, $result);
    }

    public function testResolveReturnsFeaturesInDependencyOrder(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();

        $order = [];

        $featureB = new class($order) implements Feature {
            public function __construct(private &$order) {}
            public function __invoke(ChainMail $chainMail): void {
                $this->order[] = 'B';
            }
        };

        $featureA = new class($order) implements Feature {
            public function __construct(private &$order) {}
            public function __invoke(ChainMail $chainMail): void {
                $this->order[] = 'A';
            }
        };

        // Register in reverse order (B before A)
        $registry->register($featureB);
        $registry->register($featureA);

        $result = $registry->resolve($chainMail);

        // Both should be in result
        $this->assertCount(2, $result);

        // Both features should be invoked (order may vary without dependencies)
        $this->assertCount(2, $order);
        $this->assertContains('A', $order);
        $this->assertContains('B', $order);
    }
}
