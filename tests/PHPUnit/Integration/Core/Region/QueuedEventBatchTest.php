<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Core\Region;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\RegionBuilder;
use Noem\State\Test\Integration\RegionBuilderTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Acceptance Criterion: Event queueing and batch processing works correctly
 */
#[Group('region')]
#[Group('integration')]
class QueuedEventBatchTest extends RegionBuilderTestCase
{
    #[Test]
    public function testBatchProcessingOfQueuedEvents(): void
    {
        $processedPayloads = [];
        $r = new RegionBuilder();

        $r = $r
            ->setStates('one', 'two', 'three')
            ->markInitial('one')
            ->onAction('one', function(object $t) use (&$processedPayloads) {
                $processedPayloads[] = $t->id;
            })
            ->onAction('two', function(object $t) use (&$processedPayloads) {
                $processedPayloads[] = $t->id;
            })
            ->addBuildStep(new AddTransition('one', 'two', fn(object $t): bool => $t->id >= 3))
            ->build();

        // Queue multiple events
        $r->trigger((object)['id' => 1], true);
        $r->trigger((object)['id' => 2], true);
        $r->trigger((object)['id' => 3], true);

        // Nothing processed yet
        $this->assertEmpty($processedPayloads);
        $this->assertTrue($r->isInState('one'));

        // Trigger immediate to process queue
        $r->trigger((object)['id' => 4], false);

        // All events should be processed
        $this->assertEquals([1, 2, 3, 4], $processedPayloads);
        $this->assertTrue($r->isInState('two'));
    }

    #[Test]
    public function testMixedQueuedAndImmediateEvents(): void
    {
        $sequence = [];
        $r = new RegionBuilder();

        $r = $r
            ->setStates('active')
            ->markInitial('active')
            ->onAction('active', function(object $t) use (&$sequence) {
                $sequence[] = $t->step;
            })
            ->build();

        $r->trigger((object)['step' => 1], true);  // Queued
        $r->trigger((object)['step' => 2], false); // Immediate (processes 1 and 2)
        $r->trigger((object)['step' => 3], true);  // Queued
        $r->trigger((object)['step' => 4], false); // Immediate (processes 3 and 4)

        $this->assertEquals([1, 2, 3, 4], $sequence);
    }

    #[Test]
    public function testQueuedEventsProcessedInOrder(): void
    {
        $order = [];
        $r = new RegionBuilder();

        $r = $r
            ->setStates('state')
            ->markInitial('state')
            ->onAction('state', function(object $t) use (&$order) {
                $order[] = $t->value;
            })
            ->build();

        // Queue events in specific order
        $r->trigger((object)['value' => 'first'], true);
        $r->trigger((object)['value' => 'second'], true);
        $r->trigger((object)['value' => 'third'], true);

        // Process queue
        $r->trigger((object)['value' => 'fourth'], false);

        $this->assertEquals(['first', 'second', 'third', 'fourth'], $order);
    }

    #[Test]
    public function testQueueClearedAfterProcessing(): void
    {
        $callCount = 0;
        $r = new RegionBuilder();

        $r = $r
            ->setStates('state')
            ->markInitial('state')
            ->onAction('state', function(object $t) use (&$callCount) {
                $callCount++;
            })
            ->build();

        // Queue and process
        $r->trigger((object)['id' => 1], true);
        $r->trigger((object)['id' => 2], true);
        $r->trigger((object)['id' => 3], false);

        $this->assertEquals(3, $callCount);

        // Queue should be empty now
        $r->trigger((object)['id' => 4], false);
        $this->assertEquals(4, $callCount, 'Should only process new event, not old ones');
    }

    #[Test]
    public function testTransitionsWithQueuedEvents(): void
    {
        $states = [];
        $r = new RegionBuilder();

        $r = $r
            ->setStates('one', 'two', 'three')
            ->markInitial('one')
            ->onEnter('one', function(object $t) use (&$states) { $states[] = 'one'; })
            ->onEnter('two', function(object $t) use (&$states) { $states[] = 'two'; })
            ->onEnter('three', function(object $t) use (&$states) { $states[] = 'three'; })
            ->addBuildStep(new AddTransition('one', 'two', fn(object $t): bool => $t->step === 1))
            ->addBuildStep(new AddTransition('two', 'three', fn(object $t): bool => $t->step === 2))
            ->build();

        // Queue transitions
        $r->trigger((object)['step' => 1], true);
        $r->trigger((object)['step' => 2], true);

        // Process queue
        $r->trigger((object)['step' => 0], false);

        $this->assertTrue($r->isInState('three'));
        $this->assertEquals(['one','two', 'three'], $states);
    }

    #[Test]
    public function testEventsTriggeredDuringProcessingAreQueued(): void
    {
        $processed = [];
        $r = null;

        $builder = new RegionBuilder();
        $r = $builder
            ->setStates('state')
            ->markInitial('state')
            ->onAction('state', function(object $t) use (&$processed, &$r) {
                $processed[] = $t->id;
                
                // Trigger another event during processing
                if ($t->id === 1) {
                    $r->trigger((object)['id' => 2], true);
                }
            })
            ->build();

        // Process first event which triggers second
        $r->trigger((object)['id' => 1], false);
        
        // At this point, event 2 is queued but not processed
        $this->assertEquals([1], $processed);

        // Process queue
        $r->trigger((object)['id' => 3], false);
        
        // Now both event 2 (queued earlier) and event 3 should be processed
        $this->assertEquals([1, 2, 3], $processed);
    }
}
