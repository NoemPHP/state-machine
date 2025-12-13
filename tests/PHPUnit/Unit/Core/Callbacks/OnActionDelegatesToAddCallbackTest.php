<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test: RegionBuilder.onAction() delegates to AddCallback BuildStep
 *
 * Intent: Maintains backward compatibility while transitioning to BuildStep-based API
 */
#[CoversClass(RegionBuilder::class)]
final class OnActionDelegatesToAddCallbackTest extends TestCase
{
    public function testOnActionRegistersCallbackInRegistry(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $callback = fn() => 'action-callback';

        $builder
            ->addState('idle')
            ->markInitial('idle')
            ->onAction('idle', $callback)
            ->build([]);

        $records = $registry->query(event: 'action', state: 'idle');
        $this->assertCount(1, $records);
        $this->assertSame('action', $records[0]->event);
        $this->assertSame('idle', $records[0]->state);
        $this->assertSame($callback, $records[0]->callback);
    }

    public function testOnActionWorksWithMultipleStates(): void
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
            ->onAction('idle', $idleCallback)
            ->onAction('active', $activeCallback)
            ->build([]);

        $idleRecords = $registry->query(event: 'action', state: 'idle');
        $this->assertCount(1, $idleRecords);
        $this->assertSame($idleCallback, $idleRecords[0]->callback);

        $activeRecords = $registry->query(event: 'action', state: 'active');
        $this->assertCount(1, $activeRecords);
        $this->assertSame($activeCallback, $activeRecords[0]->callback);
    }
}
