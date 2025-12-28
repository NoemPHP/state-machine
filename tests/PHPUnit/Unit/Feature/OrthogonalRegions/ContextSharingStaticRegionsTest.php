<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Static orthogonal regions share parent's ExtendedState context
 */
#[Group('orthogonal-regions')]
#[Group('context-sharing')]
class ContextSharingStaticRegionsTest extends TestCase
{
    public function testStaticRegionsShareParentContext(): void
    {
        $child1Value = null;
        $child2Value = null;

        $child1 = (new RegionBuilder())
            ->setStates('c1')
            ->markInitial('c1')
            ->onEnter('c1', function (object $t) use (&$child1Value) {
                $child1Value = $this->get('shared');
            });

        $child2 = (new RegionBuilder())
            ->setStates('c2')
            ->markInitial('c2')
            ->onEnter('c2', function (object $t) use (&$child2Value) {
                $child2Value = $this->get('shared');
            });

        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child1, $child2]
        ))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) {
                $this->set('shared', 'parent_value');
            })
            ->build();

        $region->init();

        $this->assertEquals('parent_value', $child1Value);
        $this->assertEquals('parent_value', $child2Value);
    }

    public function testStaticRegionsCanModifySharedContext(): void
    {
        $child1 = (new RegionBuilder())
            ->setStates('c1')
            ->markInitial('c1')
            ->onEnter('c1', function (object $t) {
                $value = $this->get('counter') ?? 0;
                $this->set('counter', $value + 10);
            });

        $child2 = (new RegionBuilder())
            ->setStates('c2')
            ->markInitial('c2')
            ->onEnter('c2', function (object $t) {
                $value = $this->get('counter') ?? 0;
                $this->set('counter', $value + 20);
            });

        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child1, $child2]
        ))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) {
                $this->set('counter', 0);
            })
            ->build();

        $region->init();

        // Children executed in order: 0 + 10 + 20 = 30
        $this->assertEquals(30, $region->context->get('counter'));
    }

    public function testParentCanAccessContextModifiedByChildren(): void
    {
        $parentFinalValue = null;

        $child = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) {
                $this->set('child_data', 'from_child');
            });

        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$child]
        ))
            ->setStates('parent', 'reading')
            ->markInitial('parent')
            ->onAction('parent', function (object $t) use (&$parentFinalValue) {
                $parentFinalValue = $this->get('child_data');
                return 'reading';
            })
            ->build();

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run(steps: 2);

        $this->assertEquals('from_child', $parentFinalValue);
    }

    public function testContextSharedAcrossMultipleStaticChildren(): void
    {
        $readValues = [];

        $writer = (new RegionBuilder())
            ->setStates('writer')
            ->markInitial('writer')
            ->onEnter('writer', function (object $t) {
                $this->set('message', 'hello');
            });

        $reader1 = (new RegionBuilder())
            ->setStates('reader1')
            ->markInitial('reader1')
            ->onEnter('reader1', function (object $t) use (&$readValues) {
                $readValues[] = $this->get('message');
            });

        $reader2 = (new RegionBuilder())
            ->setStates('reader2')
            ->markInitial('reader2')
            ->onEnter('reader2', function (object $t) use (&$readValues) {
                $readValues[] = $this->get('message');
            });

        $region = (new OrthogonalRegions(
            new ExtendedState(new RegionBuilder()),
            [$writer, $reader1, $reader2]
        ))
            ->setStates('parent')
            ->markInitial('parent')
            ->build();

        $region->init();

        $this->assertEquals(['hello', 'hello'], $readValues);
    }
}
