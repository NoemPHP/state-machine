<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Callbacks\CallbackType;
use Noem\State\Middleware\ChainMail;
use Noem\State\Region;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// Test double for CallbackType
class TestCallbackTypeForRegistration extends CallbackType
{
}

/**
 * Test: AddCallback registers callback in CallbackRegistry during build
 *
 * Intent: Ensures callbacks are stored centrally and accessible to features
 * during region construction
 */
#[CoversClass(AddCallback::class)]
final class AddCallbackRegistrationTest extends TestCase
{
    public function testAddCallbackRegistersInRegistry(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);
        $callback = fn() => 'test';

        $builder
            ->addState('idle')
            ->markInitial('idle')
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callback
                )
            )
            ->build([]);

        $records = $registry->query();
        $this->assertCount(1, $records);
        $this->assertSame('action', $records[0]->event);
        $this->assertSame('idle', $records[0]->state);
        $this->assertSame($callback, $records[0]->callback);
    }

    public function testAddCallbackWithCustomType(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);
        $type = TestCallbackTypeForRegistration::get();
        $callback = fn() => 'test';

        $builder
            ->addState('active')
            ->markInitial('active')
            ->addBuildStep(
                new AddCallback(
                    event: 'enter',
                    state: 'active',
                    callback: $callback,
                    type: $type
                )
            )
            ->build([]);

        $records = $registry->query();
        $this->assertCount(1, $records);
        $this->assertTrue($type->is($records[0]->type));
    }
}
