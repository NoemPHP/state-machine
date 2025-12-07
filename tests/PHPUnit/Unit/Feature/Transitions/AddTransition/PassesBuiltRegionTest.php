<?php

declare(strict_types=1);

namespace Noem\State\Tests\Unit\Feature\Transitions\AddTransition;

use Noem\State\Feature\Transitions\AddTransition;
use Noem\State\Feature\Transitions\TransitionRegistry;
use Noem\State\Feature\Transitions\TransitionsFeature;
use Noem\State\RegionBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Acceptance Criterion: AddTransition passes built region to TransitionRegistry
 */
#[Group('transitions')]
#[Group('add-transition-buildstep')]
class PassesBuiltRegionTest extends TestCase
{
    public function testPassesCorrectRegionToRegistry(): void
    {
        $builder = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('a', 'b')
            ->addBuildStep(new AddTransition('a', 'b'));

        $region = $builder->build();
        $registry = $builder->chainMail->get(TransitionRegistry::class);

        // The registry should have transitions for this specific region instance
        $transitions = $registry->getTransitionsForState($region, 'a');

        $this->assertArrayHasKey('b', $transitions);
    }

    public function testDifferentRegionsHaveIsolatedTransitions(): void
    {
        $builder1 = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('a', 'b')
            ->addBuildStep(new AddTransition('a', 'b'));

        $builder2 = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('x', 'y')
            ->addBuildStep(new AddTransition('x', 'y'));

        $region1 = $builder1->build();
        $region2 = $builder2->build();

        $registry1 = $builder1->chainMail->get(TransitionRegistry::class);
        $registry2 = $builder2->chainMail->get(TransitionRegistry::class);

        $transitions1 = $registry1->getTransitionsForState($region1, 'a');
        $transitions2 = $registry2->getTransitionsForState($region2, 'x');

        $this->assertArrayHasKey('b', $transitions1);
        $this->assertArrayHasKey('y', $transitions2);
        $this->assertArrayNotHasKey('y', $transitions1);
        $this->assertArrayNotHasKey('b', $transitions2);
    }
}
