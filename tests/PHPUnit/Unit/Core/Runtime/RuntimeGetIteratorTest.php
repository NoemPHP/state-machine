<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime.getIterator() returns events() generator
 */
#[Group('runtime')]
#[Group('runtime-events')]
class RuntimeGetIteratorTest extends TestCase
{
    public function testGetIteratorReturnsTraversable(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->build();

        $runtime = new StandardRuntime($region);

        $iterator = $runtime->getIterator();

        $this->assertInstanceOf(\Traversable::class, $iterator, 'getIterator() should return Traversable');
    }

    public function testGetIteratorReturnsGenerator(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $iterator = $runtime->getIterator();

        $this->assertInstanceOf(\Generator::class, $iterator, 'getIterator() should return Generator from events()');
    }

    public function testGetIteratorDelegatesToEvents(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'processing', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->onEnter('start', function (object $t) use (&$region) {
                $region->trigger((object)['from' => 'getIterator'], enqueue: true);
            })
            ->addBuildStep(new AddTransition('start', 'processing'))
            ->addBuildStep(new AddTransition('processing', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $events = [];
        foreach ($runtime->getIterator() as $event) {
            if (isset($event->from)) {
                $events[] = $event;
            }
        }

        $this->assertNotEmpty($events, 'getIterator() should yield events from events() method');
    }

    public function testGetIteratorProducedSameEventsAsEvents(): void
    {
        $region = (new RegionBuilder())
            ->setStates('emitting', 'processing', 'done')
            ->markInitial('emitting')
            ->markFinal('done')
            ->onEnter('emitting', function (object $t) use (&$region) {
                $region->trigger((object)['id' => 'event1'], enqueue: true);
                $region->trigger((object)['id' => 'event2'], enqueue: true);
            })
            ->addBuildStep(new AddTransition('emitting', 'processing'))
            ->addBuildStep(new AddTransition('processing', 'done'))
            ->build();

        // First runtime - use events() directly
        $runtime1 = new StandardRuntime($region);
        $eventsFromMethod = [];
        foreach ($runtime1->events() as $event) {
            if (isset($event->id)) {
                $eventsFromMethod[] = $event->id;
            }
        }

        // Second runtime - use getIterator()
        $region2 = (new RegionBuilder())
            ->setStates('emitting', 'processing', 'done')
            ->markInitial('emitting')
            ->markFinal('done')
            ->onEnter('emitting', function (object $t) use (&$region2) {
                $region2->trigger((object)['id' => 'event1'], enqueue: true);
                $region2->trigger((object)['id' => 'event2'], enqueue: true);
            })
            ->addBuildStep(new AddTransition('emitting', 'processing'))
            ->addBuildStep(new AddTransition('processing', 'done'))
            ->build();

        $runtime2 = new StandardRuntime($region2);
        $eventsFromIterator = [];
        foreach ($runtime2->getIterator() as $event) {
            if (isset($event->id)) {
                $eventsFromIterator[] = $event->id;
            }
        }

        $this->assertEquals($eventsFromMethod, $eventsFromIterator, 'getIterator() should produce same events as events()');
    }
}
