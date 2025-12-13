<?php

declare(strict_types=1);

namespace Tests\Unit\Feature\Async;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Feature\Async\AsyncCallbackType;
use Noem\State\Feature\Async\AsyncConfig;
use Noem\State\Feature\Async\AsyncFeature;
use Noem\State\Feature\Async\Priority;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\TestCase;

final class QueriesAsyncCallbacksTest extends TestCase
{
    public function testAsyncFeatureQueriesCallbackRegistryForAsyncCallbackType(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $executed = false;

        // Register async callback using AsyncCallbackType
        $builder->addBuildStep(new AddCallback(
            event: 'action',
            state: 'active',
            callback: function (object $trigger) use (&$executed) {
                $executed = true;
                yield;
            },
            type: AsyncCallbackType::get(),
            metadata: new AsyncConfig(priority: Priority::NORMAL)
        ));

        $region = $builder
            ->setStates('active')
            ->build();

        // Trigger to invoke callback
        $region->trigger(new \stdClass());

        // Verify callback was executed (AsyncFeature queried registry and found it)
        $this->assertTrue($executed, 'Async callback should be queried and executed');
    }

    public function testAsyncFeatureSkipsSyncCallbacksWhenQuerying(): void
    {
        $builder = new RegionBuilder();
        $builder->enableFeatures(new AsyncFeature());

        $syncExecuted = false;

        // Register sync callback (not AsyncCallbackType)
        $builder->onAction('active', function (object $trigger) use (&$syncExecuted) {
            $syncExecuted = true;
        });

        $region = $builder
            ->setStates('active')
            ->build();

        // Trigger
        $region->trigger(new \stdClass());

        // Sync callback should still execute (via default channel)
        $this->assertTrue($syncExecuted, 'Sync callbacks should still execute');
    }
}
