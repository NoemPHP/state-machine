<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async;

use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

final class RegistersCallbackRegistryTest extends TestCase
{
    public function testAsyncFeatureRegistersCallbackRegistrySingleton(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        // Access ChainMail from builder
        $reflection = new \ReflectionClass($builder);
        $chainMailProperty = $reflection->getProperty('chainMail');
        $chainMailProperty->setAccessible(true);
        $chainMail = $chainMailProperty->getValue($builder);

        // Verify CallbackRegistry is registered
        $registry = $chainMail->get(CallbackRegistry::class);
        $this->assertInstanceOf(CallbackRegistry::class, $registry);

        // Verify it's a singleton
        $registry2 = $chainMail->get(CallbackRegistry::class);
        $this->assertSame($registry, $registry2, 'CallbackRegistry should be singleton');
    }
}
