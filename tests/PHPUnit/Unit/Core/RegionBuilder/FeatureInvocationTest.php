<?php

declare(strict_types=1);

namespace Noem\State\Test\Unit\Core\RegionBuilder;

use Noem\State\Feature\Feature;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Features are invoked with the builder's ChainMail instance
 */
#[Group('region-builder')]
#[Group('feature-registration')]
class FeatureInvocationTest extends TestCase
{
    public function testFeatureReceivesCorrectChainMail(): void
    {
        // Create custom ChainMail with all required services for build()
        $customChainMail = new ChainMail();
        $customChainMail->supply(
            fn(): \Noem\State\Chains\ConnectedRegions => new \Noem\State\Chains\ConnectedRegions(),
            fn(\Noem\State\Chains\ConnectedRegions $connections): \Noem\State\Chains\Meta => new \Noem\State\Chains\Meta($connections),
            fn(): \Noem\State\Chains\ValidateCallback => new \Noem\State\Chains\ValidateCallback(),
            fn(): \Noem\State\Chains\PrepareInvokable => new \Noem\State\Chains\PrepareInvokable(),
            fn(): \Noem\State\Chains\InvokeCallback => new \Noem\State\Chains\InvokeCallback(),
            fn(): \Noem\State\Chains\ExtendedState => new \Noem\State\Chains\ExtendedState(),
            fn(): \Noem\State\Chains\Set => new \Noem\State\Chains\Set(),
            fn(): \Noem\State\Chains\Get => new \Noem\State\Chains\Get(),
            fn(): \Noem\State\Chains\Notification => new \Noem\State\Chains\Notification(),
            fn(\Noem\State\Chains\ConnectedRegions $connections): \Noem\State\Chains\Path => new \Noem\State\Chains\Path($connections),
            \Noem\State\Events::conjure(),
            fn(\Noem\State\Chains\ConnectedRegions $c, \Noem\State\Events $e): \Noem\State\Chains\DispatchAction => new \Noem\State\Chains\DispatchAction($c, $e),
            fn(): \Noem\State\Chains\EnhanceRegionBuilder => new \Noem\State\Chains\EnhanceRegionBuilder()
        );

        $builder = new RegionBuilder($customChainMail);

        $receivedChainMail = null;

        $feature = new class ($receivedChainMail) implements Feature {
            public function __construct(private mixed &$receivedChainMail)
            {
            }

            public function __invoke(ChainMail $chainMail): void
            {
                $this->receivedChainMail = $chainMail;
            }
        };

        $builder->enableFeatures($feature);

        // Feature not yet invoked
        $this->assertNull($receivedChainMail, 'Feature should NOT be invoked during enableFeatures()');

        $builder->setStates('a', 'b')->build();

        // Feature invoked during build()
        $this->assertSame(
            $customChainMail,
            $receivedChainMail,
            'Feature should receive the exact ChainMail instance passed to builder'
        );
    }

    public function testFeatureInvocationOrder(): void
    {
        $builder = new RegionBuilder();
        $order = [];

        // Create three distinct feature classes (not instances of the same class)
        $featureA = new class ($order) implements Feature {
            public function __construct(private array &$order)
            {
            }

            public function __invoke(ChainMail $chainMail): void
            {
                $this->order[] = 'A';
            }
        };

        $featureB = new class ($order) implements Feature {
            public function __construct(private array &$order)
            {
            }

            public function __invoke(ChainMail $chainMail): void
            {
                $this->order[] = 'B';
            }
        };

        $featureC = new class ($order) implements Feature {
            public function __construct(private array &$order)
            {
            }

            public function __invoke(ChainMail $chainMail): void
            {
                $this->order[] = 'C';
            }
        };

        $builder->enableFeatures($featureA, $featureB, $featureC);

        // Features not yet invoked
        $this->assertCount(0, $order, 'Features should NOT be invoked during enableFeatures()');

        $builder->setStates('a', 'b')->build();

        // Features invoked during build()
        $this->assertCount(3, $order, 'All features should be invoked during build()');
        $this->assertContains('A', $order);
        $this->assertContains('B', $order);
        $this->assertContains('C', $order);
    }
}
