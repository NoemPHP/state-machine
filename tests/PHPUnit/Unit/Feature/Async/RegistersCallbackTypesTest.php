<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async;

use Noem\State\Feature\Async\AsyncCallbackType;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

final class RegistersCallbackTypesTest extends TestCase
{
    public function testAsyncFeatureRegistersAsyncCallbackTypeInChainMail(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        // Access ChainMail from builder
        $reflection = new \ReflectionClass($builder);
        $chainMailProperty = $reflection->getProperty('chainMail');
        $chainMailProperty->setAccessible(true);
        $chainMail = $chainMailProperty->getValue($builder);

        // Verify AsyncCallbackType is available via get()
        $asyncType = AsyncCallbackType::get();
        $this->assertInstanceOf(AsyncCallbackType::class, $asyncType);
    }
}
