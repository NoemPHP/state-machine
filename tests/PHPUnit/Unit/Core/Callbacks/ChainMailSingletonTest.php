<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test: CallbackRegistry is registered in ChainMail as singleton
 *
 * Intent: Provides single registry instance shared across all features, ensuring
 * consistent callback storage
 */
#[CoversClass(CallbackRegistry::class)]
final class ChainMailSingletonTest extends TestCase
{
    public function testCallbackRegistryIsAvailableInChainMail(): void
    {
        $builder = new RegionBuilder();
        $registry = new CallbackRegistry();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $retrieved = $builder->chainMail->get(CallbackRegistry::class);

        $this->assertInstanceOf(CallbackRegistry::class, $retrieved);
        $this->assertSame($registry, $retrieved);
    }

    public function testCallbackRegistryIsSingletonAcrossMultipleGets(): void
    {
        $builder = new RegionBuilder();
        $registry = new CallbackRegistry();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $instance1 = $builder->chainMail->get(CallbackRegistry::class);
        $instance2 = $builder->chainMail->get(CallbackRegistry::class);

        $this->assertSame($instance1, $instance2, 'CallbackRegistry should be singleton');
    }

    public function testMultipleBuilderInstancesCanHaveDifferentRegistries(): void
    {
        $builder1 = new RegionBuilder();
        $registry1 = new CallbackRegistry();
        $builder1->chainMail->supply(fn(): CallbackRegistry => $registry1);

        $builder2 = new RegionBuilder();
        $registry2 = new CallbackRegistry();
        $builder2->chainMail->supply(fn(): CallbackRegistry => $registry2);

        $this->assertNotSame($registry1, $registry2);
        $this->assertSame($registry1, $builder1->chainMail->get(CallbackRegistry::class));
        $this->assertSame($registry2, $builder2->chainMail->get(CallbackRegistry::class));
    }
}
