<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\AddCallback;
use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\Middleware\ChainMail;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test: Multiple AddCallback steps can register different callbacks for same state
 *
 * Intent: Supports multiple callback registration without conflicts, enabling
 * modular feature composition
 */
#[CoversClass(AddCallback::class)]
final class MultipleCallbacksPerStateTest extends TestCase
{
    public function testMultipleCallbacksForSameState(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);
        $callback1 = fn() => 'first';
        $callback2 = fn() => 'second';
        $callback3 = fn() => 'third';

        $builder
            ->addState('idle')
            ->markInitial('idle')
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callback1
                )
            )
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $callback2
                )
            )
            ->addBuildStep(
                new AddCallback(
                    event: 'enter',
                    state: 'idle',
                    callback: $callback3
                )
            )
            ->build([]);

        $records = $registry->query(state: 'idle');
        $this->assertCount(3, $records);

        $actionRecords = $registry->query(state: 'idle', event: 'action');
        $this->assertCount(2, $actionRecords);
        // BuildSteps execute in reverse order (LIFO), so last added is first registered
        $this->assertSame($callback2, $actionRecords[0]->callback);
        $this->assertSame($callback1, $actionRecords[1]->callback);

        $enterRecords = $registry->query(state: 'idle', event: 'enter');
        $this->assertCount(1, $enterRecords);
        $this->assertSame($callback3, $enterRecords[0]->callback);
    }

    public function testMultipleCallbacksAcrossDifferentStates(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);
        $idleCallback = fn() => 'idle';
        $activeCallback = fn() => 'active';

        $builder
            ->addState('idle')
            ->addState('active')
            ->markInitial('idle')
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'idle',
                    callback: $idleCallback
                )
            )
            ->addBuildStep(
                new AddCallback(
                    event: 'action',
                    state: 'active',
                    callback: $activeCallback
                )
            )
            ->build([]);

        $idleRecords = $registry->query(state: 'idle');
        $this->assertCount(1, $idleRecords);
        $this->assertSame($idleCallback, $idleRecords[0]->callback);

        $activeRecords = $registry->query(state: 'active');
        $this->assertCount(1, $activeRecords);
        $this->assertSame($activeCallback, $activeRecords[0]->callback);
    }
}
