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
 * Acceptance Criterion: AddTransition registers transition in TransitionRegistry during build
 */
#[Group('transitions')]
#[Group('add-transition-buildstep')]
class RegistersInRegistryTest extends TestCase
{
    public function testRegistersTransitionDuringBuild(): void
    {
        $builder = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('idle', 'working', 'done')
            ->addBuildStep(new AddTransition('idle', 'working'));

        $region = $builder->build();
        $registry = $builder->chainMail->get(TransitionRegistry::class);

        $transitions = $registry->getTransitionsForState($region, 'idle');

        $this->assertArrayHasKey('working', $transitions);
        $this->assertCount(1, $transitions['working']);
    }

    public function testRegistersMultipleTransitions(): void
    {
        $builder = (new RegionBuilder())
            ->enableFeatures(new TransitionsFeature())
            ->setStates('idle', 'working', 'done')
            ->addBuildStep(new AddTransition('idle', 'working'))
            ->addBuildStep(new AddTransition('working', 'done'));

        $region = $builder->build();
        $registry = $builder->chainMail->get(TransitionRegistry::class);

        $transitionsFromIdle = $registry->getTransitionsForState($region, 'idle');
        $transitionsFromWorking = $registry->getTransitionsForState($region, 'working');

        $this->assertArrayHasKey('working', $transitionsFromIdle);
        $this->assertArrayHasKey('done', $transitionsFromWorking);
    }
}
