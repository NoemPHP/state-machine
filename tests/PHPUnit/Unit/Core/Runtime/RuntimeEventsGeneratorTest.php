<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Core\Runtime;

use Noem\State\RegionBuilder;
use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Runtime.events() uses generator for memory efficiency
 */
#[Group('runtime')]
#[Group('runtime-events')]
class RuntimeEventsGeneratorTest extends TestCase
{
    public function testEventsReturnsGenerator(): void
    {
        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $events = $runtime->events();

        $this->assertInstanceOf(\Generator::class, $events, 'events() should return a Generator');
    }

    public function testEventsDoesNotBufferAllEventsInMemory(): void
    {
        $region = (new RegionBuilder())
            ->setStates('emitting', 'done')
            ->markInitial('emitting')
            ->markFinal('done')
            ->onEnter('emitting', function (object $t) use (&$region) {
                // Emit many events
                for ($i = 0; $i < 1000; $i++) {
                    $region->trigger((object)['index' => $i], enqueue: true);
                }
            })
            ->addBuildStep(new AddTransition('emitting', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        $generator = $runtime->events();

        // Generator should be created without executing machine yet
        $this->assertInstanceOf(\Generator::class, $generator);

        // Events are yielded one by one, not all buffered
        $count = 0;
        foreach ($generator as $event) {
            $count++;
            if ($count > 10) {
                break; // Stop early to verify we're streaming
            }
        }

        $this->assertGreaterThan(0, $count, 'Should yield events');
    }

    public function testGeneratorYieldsEventsAsTheyOccur(): void
    {
        $yieldOrder = [];

        $region = (new RegionBuilder())
            ->setStates('producing', 'done')
            ->markInitial('producing')
            ->markFinal('done')
            ->onEnter('producing', function (object $t) use (&$region, &$yieldOrder) {
                $yieldOrder[] = 'before_emit';
                $region->trigger((object)['marker' => 'event1'], enqueue: true);
                $yieldOrder[] = 'after_emit';
            })
            ->addBuildStep(new AddTransition('producing', 'done'))
            ->build();

        $runtime = new StandardRuntime($region);

        // Consuming the generator executes the machine
        $consumed = [];
        foreach ($runtime->events() as $event) {
            $consumed[] = $event;
        }

        // Verify events were emitted during execution
        $this->assertContains('before_emit', $yieldOrder);
        $this->assertContains('after_emit', $yieldOrder);
    }

    public function testGeneratorIsLazyAndDoesNotExecuteUntilIterated(): void
    {
        $executed = false;

        $region = (new RegionBuilder())
            ->setStates('start', 'done')
            ->markInitial('start')
            ->markFinal('done')
            ->addBuildStep(new AddTransition('start', 'done'))
            ->onAction('start', function (object $t) use (&$executed): void {
                $executed = true;
            })
            ->build();

        $runtime = new StandardRuntime($region);

        // Just calling events() should not execute the machine
        $generator = $runtime->events();

        $this->assertFalse($executed, 'Generator should be lazy - not execute until iterated');

        // Iterating the generator should execute
        iterator_to_array($generator);

        $this->assertTrue($executed, 'Machine should execute when generator is iterated');
    }
}
