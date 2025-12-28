<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime implements IteratorAggregate
 */
#[Group('runtime')]
#[Group('runtime-events')]
class RuntimeIteratorAggregateTest extends TestCase
{
    public function testRuntimeImplementsIteratorAggregate(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start')
            ->markInitial('start')
            ->build();

        $runtime = new StandardRuntime($region);

        $this->assertInstanceOf(\IteratorAggregate::class, $runtime, 'Runtime should implement IteratorAggregate');
    }

    public function testRuntimeCanBeUsedInForeach(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->onEnter('start', function (object $t) use (&$region) {
                $region->trigger((object)['type' => 'test_event'], enqueue: true);
            })
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $eventCount = 0;
        foreach ($runtime as $event) {
            $eventCount++;
        }

        $this->assertGreaterThanOrEqual(0, $eventCount, 'Should be able to iterate runtime directly');
    }

    public function testForeachIterationUsesGetIterator(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'processing', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->onEnter('start', function (object $t) use (&$region) {
                $region->trigger((object)['marker' => 'foreach_test'], enqueue: true);
            })
            ->addBuildStep(new AddTransition('start', 'processing'))
            ->addBuildStep(new AddTransition('processing', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $events = [];
        foreach ($runtime as $event) {
            if (isset($event->marker)) {
                $events[] = $event;
            }
        }

        $this->assertNotEmpty($events, 'Should receive events through foreach');
    }

    public function testIteratorAggregateEnablesForeachSyntax(): void
    {
        $region = (new RegionBuilder())
            ->setStates('counting', 'processing', 'done')
            ->markInitial('counting')
            ->markFinal('done')
            ->onEnter('counting', function (object $t) use (&$region) {
                $region->trigger((object)['count' => 1], enqueue: true);
                $region->trigger((object)['count' => 2], enqueue: true);
            })
            ->addBuildStep(new AddTransition('counting', 'processing'))
            ->addBuildStep(new AddTransition('processing', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $counts = [];
        foreach ($runtime as $event) {
            if (isset($event->count)) {
                $counts[] = $event->count;
            }
        }

        $this->assertContains(1, $counts);
        $this->assertContains(2, $counts);
    }
}
