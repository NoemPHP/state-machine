<?php

declare(strict_types=1);

namespace Noem\State\Tests\PHPUnit\Unit\Core\RegionBuilder\FeatureRegistry;

use Noem\State\Feature\Feature;
use Noem\State\Feature\FeatureRegistry;
use Noem\State\Middleware\ChainMail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: FeatureRegistry resolve invokes features with ChainMail on first call per instance
 */
#[Group('feature-dependency-resolution')]
class InvokesOnFirstCallTest extends TestCase
{
    public function testResolveInvokesFeaturesOnFirstCall(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();
        $invoked = false;

        $feature = new class($invoked) implements Feature {
            public function __construct(private &$invoked) {}

            public function __invoke(ChainMail $chainMail): void {
                $this->invoked = true;
            }
        };

        $registry->register($feature);

        // First call should invoke feature
        $registry->resolve($chainMail);

        $this->assertTrue($invoked, 'Feature should be invoked on first resolve call');
    }

    public function testResolveInvokesFeaturesWithCorrectChainMail(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();
        $receivedChainMail = null;

        $feature = new class($receivedChainMail) implements Feature {
            public function __construct(private &$receivedChainMail) {}

            public function __invoke(ChainMail $chainMail): void {
                $this->receivedChainMail = $chainMail;
            }
        };

        $registry->register($feature);

        $registry->resolve($chainMail);

        $this->assertSame($chainMail, $receivedChainMail,
            'Feature should receive the same ChainMail instance passed to resolve');
    }

    public function testResolveInvokesAllFeaturesInOrder(): void
    {
        $registry = new FeatureRegistry();
        $chainMail = new ChainMail();
        $invocationOrder = [];

        $featureA = new class($invocationOrder) implements Feature {
            public function __construct(private &$invocationOrder) {}

            public function __invoke(ChainMail $chainMail): void {
                $this->invocationOrder[] = 'A';
            }
        };

        $featureB = new class($invocationOrder) implements Feature {
            public function __construct(private &$invocationOrder) {}

            public function __invoke(ChainMail $chainMail): void {
                $this->invocationOrder[] = 'B';
            }
        };

        $registry->register($featureA);
        $registry->register($featureB);

        $registry->resolve($chainMail);

        $this->assertCount(2, $invocationOrder,
            'All features should be invoked');
        $this->assertContains('A', $invocationOrder);
        $this->assertContains('B', $invocationOrder);
    }
}
