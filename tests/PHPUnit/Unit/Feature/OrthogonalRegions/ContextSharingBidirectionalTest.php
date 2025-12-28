<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Context changes are bidirectional between parent and orthogonal regions
 */
#[Group('orthogonal-regions')]
#[Group('context-sharing')]
class ContextSharingBidirectionalTest extends TestCase
{
    public function testParentWritesChildReads(): void
    {
        $childReadValue = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$childReadValue) {
                $childReadValue = $this->get('parent_wrote');
            });

        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $this->set('parent_wrote', 'value_from_parent');
                $runtime = $this->summon($childBuilder);
                $runtime->run();
            })
            ->build();

        $region->init();

        $this->assertEquals('value_from_parent', $childReadValue);
    }

    public function testChildWritesParentReads(): void
    {
        $parentReadValue = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) {
                $this->set('child_wrote', 'value_from_child');
            });

        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent', 'reading')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $runtime = $this->summon($childBuilder);
                $runtime->run();
            })
            ->onAction('parent', function (object $t) use (&$parentReadValue) {
                $parentReadValue = $this->get('child_wrote');
                return 'reading';
            })
            ->build();

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run(steps: 2);

        $this->assertEquals('value_from_child', $parentReadValue);
    }

    public function testMultipleRoundTripUpdates(): void
    {
        $communicationLog = [];

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$communicationLog) {
                $message = $this->get('message');
                $communicationLog[] = "child_received: {$message}";
                $this->set('message', 'child_response');
            });

        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent', 'round2', 'done')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$communicationLog) {
                $this->set('message', 'parent_initial');
                $runtime = $this->summon($childBuilder);
                $runtime->run();

                $response = $this->get('message');
                $communicationLog[] = "parent_received: {$response}";
            })
            ->onAction('parent', fn(object $t) => 'round2')
            ->onEnter('round2', function (object $t) use ($childBuilder, &$communicationLog) {
                $this->set('message', 'parent_second');
                $runtime = $this->summon($childBuilder);
                $runtime->run();

                $response = $this->get('message');
                $communicationLog[] = "parent_received: {$response}";
            })
            ->build();

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run(steps: 3);

        $this->assertEquals([
            'child_received: parent_initial',
            'parent_received: child_response',
            'child_received: parent_second',
            'parent_received: child_response',
        ], $communicationLog);
    }

    public function testParentAndChildAlternateUpdates(): void
    {
        $finalValue = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) {
                $count = $this->get('counter');
                $this->set('counter', $count * 2);
            });

        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder, &$finalValue) {
                $this->set('counter', 1);

                // Round 1: Parent=1, Child=2
                $this->summon($childBuilder)->run();

                // Round 2: Parent=3, Child=6
                $count = $this->get('counter');
                $this->set('counter', $count + 1);
                $this->summon($childBuilder)->run();

                // Round 3: Parent=7, Child=14
                $count = $this->get('counter');
                $this->set('counter', $count + 1);
                $this->summon($childBuilder)->run();

                $finalValue = $this->get('counter');
            })
            ->build();

        $region->init();

        $this->assertEquals(14, $finalValue); // ((1*2)+1)*2)+1)*2 = 14
    }

    public function testStaticChildrenBidirectionalCommunication(): void
    {
        $child1Value = null;
        $child2Value = null;

        $child1 = (new RegionBuilder())
            ->setStates('c1')
            ->markInitial('c1')
            ->onEnter('c1', function (object $t) use (&$child1Value) {
                $this->set('from_child1', 'hello');
                $child1Value = $this->get('from_parent');
            });

        $child2 = (new RegionBuilder())
            ->setStates('c2')
            ->markInitial('c2')
            ->onEnter('c2', function (object $t) use (&$child2Value) {
                $child2Value = $this->get('from_child1');
                $this->set('from_child2', 'world');
            });

        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child1, $child2]
        ))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) {
                $this->set('from_parent', 'start');
            })
            ->build();

        $region->init();

        $this->assertEquals('start', $child1Value);
        $this->assertEquals('hello', $child2Value);
        $this->assertEquals('world', $region->context->get('from_child2'));
    }
}
