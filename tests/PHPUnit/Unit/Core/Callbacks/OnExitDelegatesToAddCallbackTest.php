<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Callbacks;

use Noem\State\Callbacks\CallbackRegistry;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Test: RegionBuilder.onExit() delegates to AddCallback BuildStep
 *
 * Intent: Maintains backward compatibility for exit callbacks using new infrastructure
 */
#[CoversClass(RegionBuilder::class)]
final class OnExitDelegatesToAddCallbackTest extends TestCase
{
    public function testOnExitRegistersCallbackInRegistry(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $callback = fn() => 'exit-callback';

        $builder
            ->addState('done')
            ->markInitial('done')
            ->onExit('done', $callback)
            ->build([]);

        $records = $registry->query(event: 'exit', state: 'done');
        $this->assertCount(1, $records);
        $this->assertSame('exit', $records[0]->event);
        $this->assertSame('done', $records[0]->state);
        $this->assertSame($callback, $records[0]->callback);
    }

    public function testOnExitWorksWithMultipleStates(): void
    {
        $registry = new CallbackRegistry();
        $builder = new RegionBuilder();
        $builder->chainMail->supply(fn(): CallbackRegistry => $registry);

        $activeExit = fn() => 'active-exit';
        $doneExit = fn() => 'done-exit';

        $builder
            ->addState('active')
            ->addState('done')
            ->markInitial('active')
            ->onExit('active', $activeExit)
            ->onExit('done', $doneExit)
            ->build([]);

        $activeRecords = $registry->query(event: 'exit', state: 'active');
        $this->assertCount(1, $activeRecords);
        $this->assertSame($activeExit, $activeRecords[0]->callback);

        $doneRecords = $registry->query(event: 'exit', state: 'done');
        $this->assertCount(1, $doneRecords);
        $this->assertSame($doneExit, $doneRecords[0]->callback);
    }
}
