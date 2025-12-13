<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test: RegionBuilder.onEnter() delegates to AddCallback BuildStep
 *
 * Intent: Maintains backward compatibility for entry callbacks using new infrastructure
 */
#[CoversClass(RegionBuilder::class)]
final class OnEnterDelegatesToAddCallbackTest extends TestCase
{
    public function testOnEnterRegistersCallbackInRegistry(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $callback = fn() => 'enter-callback';

        $builder
            ->addState('active')
            ->markInitial('active')
            ->onEnter('active', $callback)
            ->build([]);

        $records = $registry->query(event: 'enter', state: 'active');
        $this->assertCount(1, $records);
        $this->assertSame('enter', $records[0]->event);
        $this->assertSame('active', $records[0]->state);
        $this->assertSame($callback, $records[0]->callback);
    }

    public function testOnEnterWorksWithMultipleStates(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $idleEnter = fn() => 'idle-enter';
        $activeEnter = fn() => 'active-enter';

        $builder
            ->addState('idle')
            ->addState('active')
            ->markInitial('idle')
            ->onEnter('idle', $idleEnter)
            ->onEnter('active', $activeEnter)
            ->build([]);

        $idleRecords = $registry->query(event: 'enter', state: 'idle');
        $this->assertCount(1, $idleRecords);
        $this->assertSame($idleEnter, $idleRecords[0]->callback);

        $activeRecords = $registry->query(event: 'enter', state: 'active');
        $this->assertCount(1, $activeRecords);
        $this->assertSame($activeEnter, $activeRecords[0]->callback);
    }
}
