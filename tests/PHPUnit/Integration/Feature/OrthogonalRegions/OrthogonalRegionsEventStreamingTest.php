<?php

declare(strict_types=1);

namespace Noem\State\Tests\Integration\Feature\OrthogonalRegions;

use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use Noem\State\StandardRuntime;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: OrthogonalRegions supports event streaming from parent and children
 */
#[Group('orthogonal-regions')]
#[Group('integration')]
#[Group('events')]
class OrthogonalRegionsEventStreamingTest extends TestCase
{
    public function testParentCanConsumeChildEvents(): void
    {
        $childEvents = [];

        $childBuilder = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onEnter('child', function (object $t) {
                $this->region->trigger((object)['source' => 'child', 'event' => 'entered'], enqueue: true);
            })
            ->onAction('child', function (object $t) {
                $this->region->trigger((object)['source' => 'child', 'event' => 'action'], enqueue: true);
                return 'done';
            });

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$childEvents) {
                $runtime = $this->summon($childBuilder);

                foreach ($runtime->events() as $event) {
                    if (isset($event->source) && $event->source === 'child') {
                        $childEvents[] = $event->event;
                    }
                }
            })
            ->build();

        $region->init();

        $this->assertContains('entered', $childEvents);
        $this->assertContains('action', $childEvents);
    }

    public function testMultipleChildrenCanEmitEvents(): void
    {
        $allEvents = [];

        $createChild = function ($name) {
            return (new RegionBuilder())
                ->setStates('child', 'done')
                ->markInitial('child')
                ->markFinal('done')
                ->onEnter('child', function (object $t) use ($name) {
                    $this->region->trigger((object)['from' => $name], enqueue: true);
                })
                ->onAction('child', fn(object $t) => 'done');
        };

        $child1 = $createChild('child1');
        $child2 = $createChild('child2');

        $region = (new OrthogonalRegions(new RegionBuilder(), [$child1, $child2]))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $runtime = new StandardRuntime($region);

        foreach ($runtime->events() as $event) {
            if (isset($event->from)) {
                $allEvents[] = $event->from;
            }
        }

        $this->assertContains('child1', $allEvents);
        $this->assertContains('child2', $allEvents);
    }

    public function testEventStreamingAcrossOrthogonalHierarchy(): void
    {
        $eventLog = [];

        $grandchild = (new RegionBuilder())
            ->setStates('gc')
            ->markInitial('gc')
            ->onEnter('gc', function (object $t) {
                $this->region->trigger((object)['level' => 'grandchild'], enqueue: true);
            });

        $child = (new OrthogonalRegions(new RegionBuilder(), [$grandchild]))
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) {
                $this->region->trigger((object)['level' => 'child'], enqueue: true);
            });

        $parent = (new OrthogonalRegions(new RegionBuilder(), [$child]))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) {
                $this->region->trigger((object)['level' => 'parent'], enqueue: true);
            })
            ->build();

        $runtime = new StandardRuntime($parent);

        foreach ($runtime->events() as $event) {
            if (isset($event->level)) {
                $eventLog[] = $event->level;
            }
        }

        $this->assertContains('parent', $eventLog);
        $this->assertContains('child', $eventLog);
        $this->assertContains('grandchild', $eventLog);
    }

    public function testParentEmitsEventsDuringSummon(): void
    {
        $parentEvents = [];

        $childBuilder = (new RegionBuilder())
            ->setStates('child', 'done')
            ->markInitial('child')
            ->markFinal('done')
            ->onAction('child', fn(object $t) => 'done');

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent', 'done')
            ->markInitial('parent')
            ->markFinal('done')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $this->region->trigger((object)['phase' => 'before_summon'], enqueue: true);
                $this->summon($childBuilder)->run();
                $this->region->trigger((object)['phase' => 'after_summon'], enqueue: true);
            })
            ->onAction('parent', fn(object $t) => 'done')
            ->build();

        $runtime = new StandardRuntime($region);

        foreach ($runtime->events() as $event) {
            if (isset($event->phase)) {
                $parentEvents[] = $event->phase;
            }
        }

        $this->assertContains('before_summon', $parentEvents);
        $this->assertContains('after_summon', $parentEvents);
    }

    public function testNonBlockingEventConsumptionFromChildren(): void
    {
        $consumedEvents = 0;

        $childBuilder = (new RegionBuilder())
            ->setStates('emitting', 'done')
            ->markInitial('emitting')
            ->markFinal('done')
            ->onAction('emitting', function (object $t) {
                static $count = 0;
                $count++;
                $this->region->trigger((object)['count' => $count], enqueue: true);
                return $count >= 3 ? 'done' : 'emitting';
            });

        $region = (new OrthogonalRegions(new RegionBuilder()))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$consumedEvents) {
                $runtime = $this->summon($childBuilder);

                // Consume events in non-blocking manner
                foreach ($runtime->events() as $event) {
                    if (isset($event->count)) {
                        $consumedEvents++;
                    }
                }
            })
            ->build();

        $region->init();

        $this->assertEquals(3, $consumedEvents);
    }

    public function testEventsFromStaticAndSummonedChildren(): void
    {
        $staticEvents = [];
        $summonedEvents = [];

        $staticChild = (new RegionBuilder())
            ->setStates('static')
            ->markInitial('static')
            ->onEnter('static', function (object $t) {
                $this->region->trigger((object)['type' => 'static'], enqueue: true);
            });

        $summonedBuilder = (new RegionBuilder())
            ->setStates('summoned')
            ->markInitial('summoned')
            ->onEnter('summoned', function (object $t) {
                $this->region->trigger((object)['type' => 'summoned'], enqueue: true);
            });

        $region = (new OrthogonalRegions(new RegionBuilder(), [$staticChild]))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($summonedBuilder) {
                $this->summon($summonedBuilder)->run();
            })
            ->build();

        $runtime = new StandardRuntime($region);

        foreach ($runtime->events() as $event) {
            if (isset($event->type)) {
                if ($event->type === 'static') {
                    $staticEvents[] = $event;
                } elseif ($event->type === 'summoned') {
                    $summonedEvents[] = $event;
                }
            }
        }

        $this->assertNotEmpty($staticEvents);
        $this->assertNotEmpty($summonedEvents);
    }
}
