<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\OrthogonalRegions;

use Noem\State\Feature\ExtendedState\ExtendedState;
use Noem\State\Feature\OrthogonalRegions\OrthogonalRegions;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: Summoned orthogonal regions share parent's ExtendedState context
 */
#[Group('orthogonal-regions')]
#[Group('context-sharing')]
class ContextSharingSummonedRegionsTest extends TestCase
{
    public function testSummonedRegionsShareParentContext(): void
    {
        $childValue = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) use (&$childValue) {
                $childValue = $this->get('parent_data');
            });

        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $this->set('parent_data', 'shared_value');
                $runtime = $this->summon($childBuilder);
                $runtime->run();
            })
            ->build();

        $region->init();

        $this->assertEquals('shared_value', $childValue);
    }

    public function testSummonedRegionCanModifyParentContext(): void
    {
        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) {
                $value = $this->get('counter') ?? 0;
                $this->set('counter', $value + 5);
            });

        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $this->set('counter', 10);
                $runtime = $this->summon($childBuilder);
                $runtime->run();
            })
            ->build();

        $region->init();

        $this->assertEquals(15, $region->context->get('counter'));
    }

    public function testParentSeesContextChangesFromSummonedChild(): void
    {
        $parentAfterValue = null;

        $childBuilder = (new RegionBuilder())
            ->setStates('child')
            ->markInitial('child')
            ->onEnter('child', function (object $t) {
                $this->set('modified_by', 'child');
            });

        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent', 'checking')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($childBuilder) {
                $runtime = $this->summon($childBuilder);
                $runtime->run();
            })
            ->onAction('parent', function (object $t) use (&$parentAfterValue) {
                $parentAfterValue = $this->get('modified_by');
                return 'checking';
            })
            ->build();

        $runtime = new \Noem\State\StandardRuntime($region);
        $runtime->run(steps: 2);

        $this->assertEquals('child', $parentAfterValue);
    }

    public function testMultipleSummonedRegionsShareContext(): void
    {
        $accumulatedValue = 0;

        $createChildBuilder = function ($increment) {
            return (new RegionBuilder())
                ->setStates('child')
                ->markInitial('child')
                ->onEnter('child', function (object $t) use ($increment) {
                    $current = $this->get('accumulator') ?? 0;
                    $this->set('accumulator', $current + $increment);
                });
        };

        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($createChildBuilder, &$accumulatedValue) {
                $this->set('accumulator', 0);

                $runtime1 = $this->summon($createChildBuilder(10));
                $runtime1->run();

                $runtime2 = $this->summon($createChildBuilder(20));
                $runtime2->run();

                $runtime3 = $this->summon($createChildBuilder(30));
                $runtime3->run();

                $accumulatedValue = $this->get('accumulator');
            })
            ->build();

        $region->init();

        $this->assertEquals(60, $accumulatedValue); // 10 + 20 + 30
    }

    public function testSummonedChildCanReadDataSetByPreviousSummonedChild(): void
    {
        $child2Value = null;

        $child1Builder = (new RegionBuilder())
            ->setStates('c1')
            ->markInitial('c1')
            ->onEnter('c1', function (object $t) {
                $this->set('sequence', 'first');
            });

        $child2Builder = (new RegionBuilder())
            ->setStates('c2')
            ->markInitial('c2')
            ->onEnter('c2', function (object $t) use (&$child2Value) {
                $child2Value = $this->get('sequence');
                $this->set('sequence', 'second');
            });

        $region = (new OrthogonalRegions(new ExtendedState(new RegionBuilder())))
            ->setStates('parent')
            ->markInitial('parent')
            ->onEnter('parent', function (object $t) use ($child1Builder, $child2Builder) {
                $runtime1 = $this->summon($child1Builder);
                $runtime1->run();

                $runtime2 = $this->summon($child2Builder);
                $runtime2->run();
            })
            ->build();

        $region->init();

        $this->assertEquals('first', $child2Value);
        $this->assertEquals('second', $region->context->get('sequence'));
    }
}
