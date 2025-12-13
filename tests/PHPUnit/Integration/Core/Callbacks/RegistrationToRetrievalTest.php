<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Callbacks;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Callbacks\CallbackType;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Test callback type
class TestCallbackTypeForIntegration extends CallbackType
{
}

/**
 * Test: Callbacks registered via AddCallback are queryable by features
 *
 * Intent: Validates complete flow from registration to retrieval, ensuring
 * features can access callbacks they need
 */
#[CoversClass(AddCallback::class)]
#[CoversClass(CallbackRegistry::class)]
final class RegistrationToRetrievalTest extends TestCase
{
    public function testEndToEndRegistrationAndRetrieval(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $actionCallback = fn() => 'action';
        $enterCallback = fn() => 'enter';
        $exitCallback = fn() => 'exit';

        $region = $builder
            ->addState('idle')
            ->addState('active')
            ->markInitial('idle')
            ->onAction('idle', $actionCallback)
            ->onEnter('active', $enterCallback)
            ->onExit('idle', $exitCallback)
            ->build([]);

        // Verify all callbacks registered
        $allRecords = $registry->query(region: $region);
        $this->assertCount(3, $allRecords);

        // Verify action callback
        $actionRecords = $registry->query(region: $region, event: 'action');
        $this->assertCount(1, $actionRecords);
        $this->assertSame($actionCallback, $actionRecords[0]->callback);
        $this->assertSame('idle', $actionRecords[0]->state);

        // Verify enter callback
        $enterRecords = $registry->query(region: $region, event: 'enter');
        $this->assertCount(1, $enterRecords);
        $this->assertSame($enterCallback, $enterRecords[0]->callback);
        $this->assertSame('active', $enterRecords[0]->state);

        // Verify exit callback
        $exitRecords = $registry->query(region: $region, event: 'exit');
        $this->assertCount(1, $exitRecords);
        $this->assertSame($exitCallback, $exitRecords[0]->callback);
        $this->assertSame('idle', $exitRecords[0]->state);
    }

    public function testQueryingByTypeFiltersCorrectly(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $type = TestCallbackTypeForIntegration::get();
        $customTypeCallback = fn() => 'custom';
        $defaultCallback = fn() => 'default';

        $region = $builder
            ->addState('idle')
            ->markInitial('idle')
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $customTypeCallback,
                    type: $type
                )
            )
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $defaultCallback
                )
            )
            ->build([]);

        // Query for custom type only
        $customRecords = $registry->query(region: $region, type: $type);
        $this->assertCount(1, $customRecords);
        $this->assertSame($customTypeCallback, $customRecords[0]->callback);
    }
}
