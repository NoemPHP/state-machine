<?php

declare(strict_types=1);

namespace Tests\Integration\Feature\Async;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Feature\Async\AsyncCallbackType;
use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Priority;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

final class DebugCallbackFlowTest extends TestCase
{
    public function testCallbackRegistryHasRecords(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $callback = function () {
            while (true) {
                yield;
            }
        };

        $builder->addBuildStep(
            new AddCallback(
                event: 'action',
                state: 'active',
                callback: $callback,
                type: AsyncCallbackType::get(),
                metadata: new AsyncConfig(priority: Priority::LOW)
            )
        );

        // Access registry from builder before building
        $reflection = new \ReflectionClass($builder);
        $chainMailProperty = $reflection->getProperty('chainMail');
        $chainMailProperty->setAccessible(true);
        $chainMail = $chainMailProperty->getValue($builder);

        $registry = $chainMail->get(CallbackRegistry::class);

        $region = $builder
            ->setStates('active')
            ->build();

        $records = $registry->query(
            region: $region,
            type: AsyncCallbackType::get(),
            event: 'action'
        );

        $this->assertCount(1, $records, 'Registry should have 1 async callback record');
        $this->assertSame($callback, $records[0]->callback, 'Callback should match');
    }
}
